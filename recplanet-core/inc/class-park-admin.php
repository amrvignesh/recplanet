<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * The park edit screen for editors: a "Park details" box with the fields the database needs, required ones marked
 * and enforced, the place terms set from the fields on save, and the index row kept in step (see Index).
 * Parks use the classic editor: the description is plain paragraphs, and required inputs block a half-filled save.
 */
class Park_Admin {

	const NONCE = 'rp_park_details';

	/** Field => [label, type, required, help]. Country, state and city are the location the old database required. */
	private static function fields(): array {
		return [
			'rp_country'     => [ 'Country', 'country', true, 'Two-letter code. Almost everything is in the United States.' ],
			'rp_state'       => [ 'State', 'state', true, 'Required for the United States; a province or region code elsewhere.' ],
			'rp_county'      => [ 'County', 'text', false, 'Optional. "Dane County", "Orleans Parish".' ],
			'rp_city'        => [ 'City', 'text', true, 'The city or town the park is listed under. Its page is /state/city/.' ],
			'rp_street'      => [ 'Street address', 'text', false, '' ],
			'rp_postal'      => [ 'Postal code', 'text', false, '' ],
			'rp_acreage'     => [ 'Acreage', 'acres', false, 'Feeds the acre counter the moment the park is published. Leave empty when unknown.' ],
			'rp_lat'         => [ 'Latitude', 'coord', false, 'Decimal degrees, for the map and Near Me. Both or neither.' ],
			'rp_lng'         => [ 'Longitude', 'coord', false, '' ],
			'rp_website'     => [ 'Website', 'url', false, 'The park&rsquo;s own page, if it has one.' ],
			'rp_verified_on' => [ 'Last verified', 'date', false, 'When the details were last checked against the source.' ],
		];
	}

	public static function init(): void {
		add_filter( 'use_block_editor_for_post_type', fn( $use, $type ) => POST_PARK === $type ? false : $use, 10, 2 );
		add_action( 'add_meta_boxes_' . POST_PARK, [ __CLASS__, 'boxes' ] );
		add_action( 'save_post_' . POST_PARK, [ __CLASS__, 'save' ], 10, 2 );
		add_filter( 'redirect_post_location', [ __CLASS__, 'redirect' ], 10, 2 );
		add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
		add_filter( 'enter_title_here', fn( $t, $post ) => POST_PARK === $post->post_type ? 'Park name (required)' : $t, 10, 2 );
		add_filter( 'wp_terms_checklist_args', fn( $a ) => TAX_ACTIVITY === ( $a['taxonomy'] ?? '' ) ? array_merge( $a, [ 'checked_ontop' => false ] ) : $a );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_filter( 'wp_insert_post_data', [ __CLASS__, 'slug_data' ], 10, 2 );
	}

	/** The park edit screen: MapLibre for the coordinates picker; the activity list as a plain alphabetical checklist. */
	public static function assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || POST_PARK !== ( get_current_screen()->post_type ?? '' ) ) {
			return;
		}
		wp_enqueue_style( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css', [], '4.7.1' );
		wp_enqueue_script( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js', [], '4.7.1', true );
		wp_add_inline_script( 'maplibre', self::picker_js() );
		wp_add_inline_style( 'wp-admin', '#rp_activity-adder,#rp_activity-tabs{display:none}#rp_activity-all{max-height:420px}'
			. '#rpMapWrap label{display:block;font-weight:600;margin-bottom:3px}#rpMap{height:340px;border:1px solid #c3c4c7;border-radius:4px;background:#eef3ea}#rpMapBar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}#rpMapMsg{margin:0;color:#646970;font-size:12px}' );
	}

	/**
	 * A park saved as a draft before its name is typed gets its post ID as a slug from WordPress and keeps it (/ma/town/72766).
	 * Once there is a name, a slug that is empty or all digits is replaced by one made from the name.
	 */
	public static function slug_data( array $data, array $postarr ): array {
		if ( POST_PARK !== ( $data['post_type'] ?? '' ) || 'auto-draft' === ( $data['post_status'] ?? '' ) ) {
			return $data;
		}
		$title = trim( (string) ( $data['post_title'] ?? '' ) );
		$name  = (string) ( $data['post_name'] ?? '' );
		$slug  = sanitize_title( $title );
		if ( '' !== $slug && ! ctype_digit( $slug ) && ( '' === $name || ctype_digit( $name ) ) ) {
			$data['post_name'] = wp_unique_post_slug( $slug, (int) ( $postarr['ID'] ?? 0 ), (string) $data['post_status'], POST_PARK, (int) ( $data['post_parent'] ?? 0 ) );
		}
		return $data;
	}

	// ------------------------------------------------------------------ edit-screen helpers (REST, editors only)

	public static function routes(): void {
		$perm = fn() => current_user_can( 'edit_rp_parks' );
		register_rest_route( 'recplanet/v1', '/admin/center', [ 'methods' => 'GET', 'permission_callback' => $perm, 'callback' => fn( \WP_REST_Request $r ) => new \WP_REST_Response( self::place_center( (string) $r['country'], (string) $r['state'], (string) $r['city'] ) ) ] );
		register_rest_route( 'recplanet/v1', '/admin/postal', [ 'methods' => 'GET', 'permission_callback' => $perm, 'callback' => fn( \WP_REST_Request $r ) => new \WP_REST_Response( self::postal_lookup( (string) $r['postal'], (int) $r['exclude'] ) ?: [] ) ] );
		register_rest_route( 'recplanet/v1', '/admin/geocode', [ 'methods' => 'GET', 'permission_callback' => $perm, 'callback' => fn( \WP_REST_Request $r ) => new \WP_REST_Response( self::geocode( (string) $r['q'] ) ) ] );
	}

	/** Where the map opens for a park without coordinates: the middle of its city's parks, else its state's, else its country's, else the US. */
	public static function place_center( string $country, string $state, string $city ): array {
		global $wpdb;
		$t       = table( 'park_index' );
		$country = strtolower( trim( $country ) ) ?: 'us';
		$state   = strtoupper( trim( $state ) );
		$city    = trim( $city );
		$tries   = [];
		if ( '' !== $city && '' !== $state ) {
			$tries[] = [ 'country = %s AND state = %s AND city = %s', [ $country, $state, $city ], 12 ];
		}
		if ( '' !== $state ) {
			$tries[] = [ 'country = %s AND state = %s', [ $country, $state ], 6 ];
		}
		$tries[] = [ 'country = %s', [ $country ], 4 ];
		foreach ( $tries as [ $where, $args, $zoom ] ) {
			$r = $wpdb->get_row( $wpdb->prepare( "SELECT AVG(lat) lat, AVG(lng) lng, COUNT(*) n FROM $t WHERE $where AND lat IS NOT NULL", $args ) );
			if ( $r && (int) $r->n > 0 ) {
				return [ 'lat' => round( (float) $r->lat, 5 ), 'lng' => round( (float) $r->lng, 5 ), 'zoom' => $zoom, 'n' => (int) $r->n ];
			}
		}
		return [ 'lat' => 39.5, 'lng' => -98.35, 'zoom' => 3.6, 'n' => 0 ];
	}

	/** The place other parks with this postal code are in: the commonest country, state, county and city, and how many agree. */
	public static function postal_lookup( string $postal, int $exclude = 0 ): ?array {
		global $wpdb;
		$postal = trim( $postal );
		if ( '' === $postal ) {
			return null;
		}
		$sql = "SELECT i.country, i.state, i.county, i.city, COUNT(*) n FROM {$wpdb->postmeta} z JOIN " . table( 'park_index' ) . " i ON i.post_id = z.post_id
			WHERE z.meta_key = 'rp_postal' AND z.meta_value %OP% AND z.post_id <> %d AND i.city <> '' GROUP BY i.country, i.state, i.county, i.city ORDER BY n DESC LIMIT 1";
		$row = $wpdb->get_row( $wpdb->prepare( str_replace( '%OP%', '= %s', $sql ), $postal, $exclude ) );
		if ( ! $row && preg_match( '/^(\d{5})/', $postal, $m ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( str_replace( '%OP%', 'LIKE %s', $sql ), $m[1] . '%', $exclude ) );   // ZIP+4 and the plain ZIP are one place
		}
		return $row ? [ 'country' => $row->country, 'state' => $row->state, 'county' => $row->county, 'city' => $row->city, 'n' => (int) $row->n ] : null;
	}

	/** A street address to a point through Google Geocoding, from the server so the key never reaches the browser. Cached for a week. */
	public static function geocode( string $q ): array {
		$q = trim( (string) preg_replace( '/\s+/', ' ', $q ) );
		if ( '' === $q ) {
			return [ 'error' => 'Nothing to look up.' ];
		}
		$key = Settings::geocoding_key() ?: Settings::maps_key();
		if ( '' === $key ) {
			return [ 'error' => 'No Google key is set under RecPlanet › Settings.' ];
		}
		$ck  = 'rp_geo_' . md5( $q );
		$hit = get_transient( $ck );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$res = wp_remote_get( 'https://maps.googleapis.com/maps/api/geocode/json?address=' . rawurlencode( $q ) . '&key=' . rawurlencode( $key ), [ 'timeout' => 10 ] );
		if ( is_wp_error( $res ) ) {
			return [ 'error' => 'Google did not answer: ' . $res->get_error_message() ];
		}
		$j = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $j['results'][0]['geometry']['location'] ) ) {
			$st  = (string) ( $j['status'] ?? 'no answer' );
			$why = (string) ( $j['error_message'] ?? '' );
			if ( 'ZERO_RESULTS' === $st ) {
				return [ 'error' => 'Google found nothing for that address.' ];
			}
			$hint = 'REQUEST_DENIED' === $st && false !== stripos( $why, 'referer' ) ? ' The Maps key is restricted to the website; add a server key under RecPlanet › Settings › Geocoding key.' : '';
			return [ 'error' => "Google answered $st." . ( $why ? " $why" : '' ) . $hint ];
		}
		$loc = $j['results'][0]['geometry']['location'];
		$out = [ 'lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'formatted' => (string) ( $j['results'][0]['formatted_address'] ?? '' ) ];
		set_transient( $ck, $out, WEEK_IN_SECONDS );
		return $out;
	}

	private static function picker_js(): string {
		return <<<'JS'
(function(){
var wrap=document.getElementById('rpMapWrap');if(!wrap)return;
var cfg={};try{cfg=JSON.parse(wrap.getAttribute('data-cfg')||'{}');}catch(e){}
var latEl=document.getElementById('rp_lat'),lngEl=document.getElementById('rp_lng'),msg=document.getElementById('rpMapMsg');
var hdr={'X-WP-Nonce':cfg.nonce};
function say(t,bad){msg.textContent=t;msg.style.color=bad?'#d63638':'#646970';}
function get(path){return fetch(cfg.rest+path,{headers:hdr,credentials:'same-origin'}).then(function(r){return r.json();});}
/* postal code: the city, county and state other parks with that code are in */
var postal=document.getElementById('rp_postal'),country=document.getElementById('rp_country'),stUs=document.getElementById('rp_state_us'),stOther=document.getElementById('rp_state_other'),county=document.getElementById('rp_county'),city=document.getElementById('rp_city'),street=document.getElementById('rp_street');
function stateVal(){return country.value==='us'?stUs.value:stOther.value;}
function setState(v){if(country.value==='us')stUs.value=v;else stOther.value=v;}
if(postal){postal.addEventListener('blur',function(){
var z=postal.value.trim();if(!z||city.value||county.value||stateVal())return;
get('postal?postal='+encodeURIComponent(z)+'&exclude='+cfg.id).then(function(d){
if(!d||!d.city)return;
var before={country:country.value,state:stateVal(),county:county.value,city:city.value};
if(d.country){country.value=d.country;country.dispatchEvent(new Event('change'));}
if(d.state)setState(d.state);
if(d.county&&!county.value)county.value=d.county;
if(!city.value)city.value=d.city;
say('City and state filled from '+d.n+' other '+(d.n===1?'place':'places')+' with postal code '+z+'. ');
var u=document.createElement('a');u.href='#';u.textContent='Undo';u.addEventListener('click',function(e){e.preventDefault();country.value=before.country;country.dispatchEvent(new Event('change'));setState(before.state);county.value=before.county;city.value=before.city;say('');});
msg.appendChild(u);
recenter();
}).catch(function(){});
});}
/* the map */
if(typeof maplibregl==='undefined'){say('The map library did not load. Type the coordinates by hand.',true);return;}
var el=document.getElementById('rpMap'),c=cfg.center||{lat:39.5,lng:-98.35,zoom:3.6},has=typeof cfg.lat==='number'&&typeof cfg.lng==='number';
var map=new maplibregl.Map({container:el,style:'https://tiles.openfreemap.org/styles/liberty',center:has?[cfg.lng,cfg.lat]:[c.lng,c.lat],zoom:has?15:c.zoom,attributionControl:false});
map.addControl(new maplibregl.NavigationControl({showCompass:false}),'top-right');
map.addControl(new maplibregl.AttributionControl({compact:true}));
var pin=null;
function write(lat,lng){latEl.value=lat.toFixed(6);lngEl.value=lng.toFixed(6);say('Point set at '+latEl.value+', '+lngEl.value+'. Save the park to keep it.');}
function place(lat,lng,doWrite){
if(!pin){pin=new maplibregl.Marker({draggable:true,color:'#E85305'}).setLngLat([lng,lat]).addTo(map);pin.on('dragend',function(){var p=pin.getLngLat();write(p.lat,p.lng);});}
else pin.setLngLat([lng,lat]);
if(doWrite)write(lat,lng);
}
if(has)place(cfg.lat,cfg.lng,false);
map.on('click',function(e){place(e.lngLat.lat,e.lngLat.lng,true);});
function fromFields(){var la=parseFloat(latEl.value),ln=parseFloat(lngEl.value);if(isFinite(la)&&isFinite(ln)&&Math.abs(la)<=90&&Math.abs(ln)<=180){place(la,ln,false);map.easeTo({center:[ln,la],zoom:Math.max(map.getZoom(),12)});}}
latEl.addEventListener('change',fromFields);lngEl.addEventListener('change',fromFields);
document.getElementById('rpMapClear').addEventListener('click',function(){latEl.value='';lngEl.value='';if(pin){pin.remove();pin=null;}say('Point cleared. The park has no coordinates until one is set.');});
function recenter(){if(pin)return;get('center?country='+encodeURIComponent(country.value)+'&state='+encodeURIComponent(stateVal())+'&city='+encodeURIComponent(city.value)).then(function(d){if(d&&d.lat)map.easeTo({center:[d.lng,d.lat],zoom:d.zoom});}).catch(function(){});}
city.addEventListener('change',recenter);stUs.addEventListener('change',recenter);
document.getElementById('rpGeocode').addEventListener('click',function(){
var q=[street?street.value:'',city.value,stateVal(),postal?postal.value:'',country.value.toUpperCase()].map(function(s){return (s||'').trim();}).filter(Boolean).join(', ');
if(!q){say('Fill in an address, a city or a state first.',true);return;}
say('Looking up '+q+'\u2026');
get('geocode?q='+encodeURIComponent(q)).then(function(d){if(d&&typeof d.lat==='number'){place(d.lat,d.lng,true);map.flyTo({center:[d.lng,d.lat],zoom:15});say('Found '+(d.formatted||q)+'. Drag the pin if it is off; save to keep it.');}else say((d&&d.error)||'Nothing found for that address.',true);}).catch(function(){say('The lookup failed.',true);});
});
if(window.jQuery)jQuery(document).on('postbox-toggled',function(){map.resize();});
setTimeout(function(){map.resize();},400);
})();
JS;
	}

	public static function boxes(): void {
		add_meta_box( 'rp_park_details', 'Park details', [ __CLASS__, 'render' ], POST_PARK, 'normal', 'high' );
		add_meta_box( 'rp_park_steward', 'Managed by', [ __CLASS__, 'steward_box' ], POST_PARK, 'side', 'high' );
		remove_meta_box( TAX_PLACE . 'div', POST_PARK, 'side' );     // set from the fields above, never by hand
	}

	/**
	 * One steward per park, found by typing: the thousands on file are searched as you type, ordered by how many
	 * places each manages, and a name that is not on file can be added under the level its wording implies.
	 */
	public static function steward_box( \WP_Post $post ): void {
		$cur = null;
		foreach ( wp_get_object_terms( $post->ID, TAX_STEWARD ) as $t ) {
			if ( ! $cur || $t->parent ) {
				$cur = $t;
			}
		}
		$levels = [];
		foreach ( get_terms( [ 'taxonomy' => TAX_STEWARD, 'parent' => 0, 'hide_empty' => false ] ) as $t ) {
			$levels[ $t->term_id ] = $t->slug;
		}
		$cfg = [
			'rest'    => esc_url_raw( rest_url( 'wp/v2/' . TAX_STEWARD ) ),
			'labels'  => STEWARD_LEVELS,
			'levels'  => $levels,
			'current' => $cur ? [ 'id' => $cur->term_id, 'name' => $cur->name, 'level' => $cur->parent ? ( $levels[ $cur->parent ] ?? 'other' ) : Taxonomies::classify_ownership( $cur->name )[0], 'count' => $cur->count ] : null,
		];
		$total = max( 0, (int) wp_count_terms( [ 'taxonomy' => TAX_STEWARD, 'hide_empty' => false ] ) - count( $levels ) );
		echo '<style>#rpStw{position:relative}#rpStw .rp-chip{display:flex;align-items:center;gap:8px;padding:6px 8px;border:1px solid #c3c4c7;border-radius:4px;background:#f6f7f7;margin-bottom:6px}#rpStw .rp-chip b{flex:1}#rpStw .rp-chip small{color:#646970}#rpStw .rp-chip button{border:0;background:none;cursor:pointer;font-size:18px;line-height:1;color:#646970;padding:0 2px}#rpStw .rp-chip button:hover{color:#d63638}#rpStw ul{position:absolute;left:0;right:0;z-index:100;margin:2px 0 0;padding:2px;list-style:none;background:#fff;border:1px solid #8c8f94;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.15);max-height:260px;overflow:auto}#rpStw ul[hidden]{display:none}#rpStw li{padding:6px 8px;cursor:pointer;border-radius:3px;display:flex;flex-direction:column;margin:0}#rpStw li small{color:#646970}#rpStw li.hi,#rpStw li:hover{background:#f0f6fc}#rpStw li.new span{color:#2271b1}#rpStwNew{margin:8px 0 0}#rpStwNew select{width:100%}</style>';
		echo '<div id="rpStw">';
		echo '<input type="hidden" name="rp_steward_id" id="rp_steward_id" value="' . ( $cur ? (int) $cur->term_id : 0 ) . '">';
		echo '<input type="hidden" name="rp_steward_new" id="rp_steward_new" value="">';
		echo '<div id="rpStwCur"></div>';
		echo '<input type="text" id="rpStwQ" class="widefat" autocomplete="off" spellcheck="false" role="combobox" aria-label="Find a steward" aria-expanded="false" aria-controls="rpStwList" aria-autocomplete="list" placeholder="Type a city, county or agency">';
		echo '<ul id="rpStwList" role="listbox" aria-label="Stewards" hidden></ul>';
		echo '<p id="rpStwNew" hidden><label for="rp_steward_level"><strong>Level of the new steward</strong></label><select name="rp_steward_level" id="rp_steward_level">';
		foreach ( STEWARD_LEVELS as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="description">The city, county, state agency, federal agency or other body that runs the park. ' . esc_html( number_format( $total ) ) . ' are on file; type part of a name to find one, or add a name that is not there yet.</p>';
		echo '</div>';
		echo '<script>var RP_STW_CFG=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . ';' . "\n" . <<<'JS'
(function(){
var cfg=RP_STW_CFG;
var idEl=document.getElementById('rp_steward_id'),newEl=document.getElementById('rp_steward_new'),lvlWrap=document.getElementById('rpStwNew'),lvlSel=document.getElementById('rp_steward_level'),cur=document.getElementById('rpStwCur'),q=document.getElementById('rpStwQ'),list=document.getElementById('rpStwList');
var items=[],hi=-1,req=0,timer=0;
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function classify(n){if(/^(City|Town|Village|Borough|Township) of /i.test(n))return 'city';if(/\bCounty\b|\bParish\b/i.test(n))return 'county';if(/\b(City|Town|Village|Borough|Township)$/i.test(n))return 'city';if(/^(State|Commonwealth) of /i.test(n))return 'state';if(/United States|U\.?S\.? (Government|Forest|Fish|Army|Navy|Air Force)|Federal|National Park Service|Bureau of|Forest Service/i.test(n))return 'federal';if(/Tribe|Tribal|Nation\b|Reservation/i.test(n))return 'tribal';return 'other';}
function label(l){return cfg.labels[l]||'Other';}
function show(sel){
if(!sel){cur.innerHTML='';q.placeholder='Type a city, county or agency';lvlWrap.hidden=true;return;}
cur.innerHTML='<span class="rp-chip"><b>'+esc(sel.name)+'</b><small>'+(sel.isNew?'new \u00b7 '+esc(label(sel.level)):esc(label(sel.level))+' \u00b7 '+Number(sel.count||0).toLocaleString('en-US')+' places')+'</small><button type="button" aria-label="Remove steward">&times;</button></span>';
q.placeholder='Change to\u2026';lvlWrap.hidden=!sel.isNew;
}
function pickTerm(s){idEl.value=s.id;newEl.value='';show(s);}
function pickNew(name){idEl.value=0;newEl.value=name;lvlSel.value=classify(name);show({name:name,level:lvlSel.value,isNew:true});}
function clear(){idEl.value=0;newEl.value='';show(null);}
function open(on){list.hidden=!on;q.setAttribute('aria-expanded',on?'true':'false');if(!on){hi=-1;q.removeAttribute('aria-activedescendant');}}
function suggest(){
var s=q.value.trim(),id=++req;
if(!s){open(false);return;}
fetch(cfg.rest+'?search='+encodeURIComponent(s)+'&per_page=12&orderby=count&order=desc&_fields=id,name,parent,count',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
if(id!==req||document.activeElement!==q)return;
items=(Array.isArray(d)?d:[]).filter(function(t){return t.parent;}).map(function(t){return {id:t.id,name:t.name,level:cfg.levels[t.parent]||'other',count:t.count};});
var exact=items.some(function(t){return t.name.toLowerCase()===s.toLowerCase();});
if(!exact)items.push({isNew:true,name:s,level:classify(s)});
hi=-1;
list.innerHTML=items.map(function(t,i){
var m=t.name.toLowerCase().indexOf(s.toLowerCase()),nm=(m<0||t.isNew)?esc(t.name):esc(t.name.slice(0,m))+'<b>'+esc(t.name.slice(m,m+s.length))+'</b>'+esc(t.name.slice(m+s.length));
return '<li role="option" id="rpStwOpt'+i+'" data-i="'+i+'" aria-selected="false"'+(t.isNew?' class="new"':'')+'><span>'+(t.isNew?'Add \u201c'+nm+'\u201d as a new steward':nm)+'</span><small>'+(t.isNew?'under '+esc(label(t.level)).toLowerCase():esc(label(t.level))+' \u00b7 '+Number(t.count).toLocaleString('en-US')+' places')+'</small></li>';
}).join('');
open(true);
});
}
function move(d){var o=list.querySelectorAll('[role=option]');if(!o.length)return;hi=(hi+d+o.length)%o.length;Array.prototype.forEach.call(o,function(x,i){x.classList.toggle('hi',i===hi);x.setAttribute('aria-selected',i===hi?'true':'false');});q.setAttribute('aria-activedescendant',o[hi].id);o[hi].scrollIntoView({block:'nearest'});}
function choose(i){var t=items[i];if(!t)return;if(t.isNew)pickNew(t.name);else pickTerm(t);q.value='';open(false);}
q.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(suggest,160);});
q.addEventListener('keydown',function(e){
if(e.key==='ArrowDown'){e.preventDefault();if(list.hidden)suggest();else move(1);}
else if(e.key==='ArrowUp'){e.preventDefault();move(-1);}
else if(e.key==='Enter'){e.preventDefault();if(items.length&&!list.hidden)choose(hi>=0?hi:0);}
else if(e.key==='Escape'){open(false);}
});
list.addEventListener('mousedown',function(e){e.preventDefault();var li=e.target.closest('[role=option]');if(li)choose(parseInt(li.getAttribute('data-i'),10));});
cur.addEventListener('click',function(e){if(e.target.closest('button'))clear();});
lvlSel.addEventListener('change',function(){if(newEl.value)show({name:newEl.value,level:lvlSel.value,isNew:true});});
document.addEventListener('click',function(e){if(!e.target.closest('#rpStw'))open(false);});
show(cfg.current);
})();
JS
		. '</script>';
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$v = [];
		foreach ( array_keys( self::fields() ) as $k ) {
			$v[ $k ] = (string) get_post_meta( $post->ID, $k, true );
		}
		$v['rp_country'] = $v['rp_country'] ?: 'us';
		$countries = self::countries();
		$states    = us_states();
		echo '<style>.rp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 18px;margin-top:8px}.rp-grid label{display:block;font-weight:600;margin-bottom:3px}.rp-grid .req{color:#d63638}.rp-grid input,.rp-grid select{width:100%}.rp-grid p{margin:4px 0 0;color:#646970;font-size:12px}.rp-wide{grid-column:1/-1}</style>';
		echo '<p>Fields marked <span style="color:#d63638">*</span> are required; a park cannot be published without them. Activities and who manages it (Managed by) are in the boxes to the right. Facilities are tags: type them in the Facilities box separated by commas (playgrounds, boat ramps), and pick from the suggestions when one appears. The description is the editor above; the photo is the featured image.</p><div class="rp-grid">';
		foreach ( self::fields() as $key => [ $label, $type, $req, $help ] ) {
			$id   = esc_attr( $key );
			$star = $req ? ' <span class="req" aria-hidden="true">*</span>' : '';
			echo '<div' . ( 'rp_website' === $key ? ' class="rp-wide"' : '' ) . '><label for="' . $id . '">' . esc_html( $label ) . $star . '</label>';
			switch ( $type ) {
				case 'country':
					echo '<select id="' . $id . '" name="' . $id . '" required>';
					foreach ( $countries as $code => $name ) {
						echo '<option value="' . esc_attr( $code ) . '"' . selected( $v[ $key ], $code, false ) . '>' . esc_html( $name ) . ' (' . esc_html( strtoupper( $code ) ) . ')</option>';
					}
					echo '</select>';
					break;
				case 'state':
					echo '<select id="rp_state_us" name="rp_state_us" required>';
					echo '<option value="">Choose a state</option>';
					foreach ( $states as $code => $name ) {
						echo '<option value="' . esc_attr( $code ) . '"' . selected( $v[ $key ], $code, false ) . '>' . esc_html( $name ) . ' (' . esc_html( $code ) . ')</option>';
					}
					echo '</select>';
					echo '<input id="rp_state_other" name="rp_state_other" type="text" maxlength="8" placeholder="Province or region code" value="' . esc_attr( 'us' === $v['rp_country'] ? '' : $v[ $key ] ) . '" style="display:none;text-transform:uppercase">';
					break;
				case 'acres':
					echo '<input id="' . $id . '" name="' . $id . '" type="number" step="0.01" min="0" inputmode="decimal" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				case 'coord':
					echo '<input id="' . $id . '" name="' . $id . '" type="number" step="any" min="' . ( 'rp_lat' === $key ? '-90' : '-180' ) . '" max="' . ( 'rp_lat' === $key ? '90' : '180' ) . '" inputmode="decimal" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				case 'url':
					echo '<input id="' . $id . '" name="' . $id . '" type="url" placeholder="https://" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				case 'date':
					echo '<input id="' . $id . '" name="' . $id . '" type="date" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				default:
					echo '<input id="' . $id . '" name="' . $id . '" type="text" maxlength="120" value="' . esc_attr( $v[ $key ] ) . '"' . ( $req ? ' required' : '' ) . '>';
			}
			if ( $help ) {
				echo '<p>' . wp_kses( $help, [ 'a' => [ 'href' => [] ] ] ) . '</p>';
			}
			echo '</div>';
		}
		$cfg = [ 'lat' => '' === $v['rp_lat'] ? null : (float) $v['rp_lat'], 'lng' => '' === $v['rp_lng'] ? null : (float) $v['rp_lng'], 'center' => self::place_center( $v['rp_country'], $v['rp_state'], $v['rp_city'] ), 'rest' => esc_url_raw( rest_url( 'recplanet/v1/admin/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'id' => $post->ID ];
		echo '<div id="rpMapWrap" class="rp-wide" data-cfg="' . esc_attr( wp_json_encode( $cfg ) ) . '"><label>Coordinates on the map</label><div id="rpMap"></div><div id="rpMapBar"><button type="button" class="button" id="rpGeocode">Find by address</button><button type="button" class="button" id="rpMapClear">Clear point</button><p id="rpMapMsg">Click the map to place the park, or drag the pin; the latitude and longitude above follow. Enter a postal code and the city and state fill in from other parks with that code.</p></div></div>';
		echo '</div>';
		echo '<script>(function(){var c=document.getElementById("rp_country"),us=document.getElementById("rp_state_us"),o=document.getElementById("rp_state_other");function t(){var isUs=c.value==="us";us.style.display=isUs?"":"none";us.required=isUs;o.style.display=isUs?"none":"";}c.addEventListener("change",t);t();})();</script>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) || ! current_user_can( 'edit_rp_parks' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$country = strtolower( sanitize_key( $_POST['rp_country'] ?? 'us' ) ) ?: 'us';
		$state   = strtoupper( sanitize_text_field( wp_unslash( 'us' === $country ? ( $_POST['rp_state_us'] ?? '' ) : ( $_POST['rp_state_other'] ?? '' ) ) ) );
		if ( 'us' === $country && ! isset( us_states()[ $state ] ) ) {
			$state = '';
		}
		$meta = [
			'rp_country'     => $country,
			'rp_state'       => $state,
			'rp_county'      => sanitize_text_field( wp_unslash( $_POST['rp_county'] ?? '' ) ),
			'rp_city'        => sanitize_text_field( wp_unslash( $_POST['rp_city'] ?? '' ) ),
			'rp_street'      => sanitize_text_field( wp_unslash( $_POST['rp_street'] ?? '' ) ),
			'rp_postal'      => sanitize_text_field( wp_unslash( $_POST['rp_postal'] ?? '' ) ),
			'rp_acreage'     => '' === trim( (string) ( $_POST['rp_acreage'] ?? '' ) ) ? '' : round( max( 0, (float) $_POST['rp_acreage'] ), 2 ),
			'rp_lat'         => '' === trim( (string) ( $_POST['rp_lat'] ?? '' ) ) ? '' : (float) $_POST['rp_lat'],
			'rp_lng'         => '' === trim( (string) ( $_POST['rp_lng'] ?? '' ) ) ? '' : (float) $_POST['rp_lng'],
			'rp_website'     => esc_url_raw( wp_unslash( $_POST['rp_website'] ?? '' ) ),
			'rp_verified_on' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_POST['rp_verified_on'] ?? '' ) ) ? (string) $_POST['rp_verified_on'] : '',
		];
		if ( '' === $meta['rp_lat'] || '' === $meta['rp_lng'] ) {
			$meta['rp_lat'] = $meta['rp_lng'] = '';       // both or neither
		}
		foreach ( $meta as $k => $val ) {
			update_post_meta( $post_id, $k, $val );
		}
		// The place terms follow the fields.
		$terms = Taxonomies::place_terms( $country, $state, $meta['rp_county'], $meta['rp_city'] );
		if ( $terms ) {
			wp_set_object_terms( $post_id, $terms, TAX_PLACE );
		}
		// Managed by: one steward, picked from the list or added by name under a level (Index reads it at priority 20).
		if ( isset( $_POST['rp_steward_id'] ) ) {
			$sid = (int) $_POST['rp_steward_id'];
			$new = sanitize_text_field( wp_unslash( $_POST['rp_steward_new'] ?? '' ) );
			if ( '' !== $new ) {
				$level = sanitize_key( $_POST['rp_steward_level'] ?? '' );
				$sid   = Taxonomies::steward_term( array_key_exists( $level, STEWARD_LEVELS ) ? $level : Taxonomies::classify_ownership( $new )[0], $new );
			}
			$st = $sid ? get_term( $sid, TAX_STEWARD ) : null;
			wp_set_object_terms( $post_id, $st instanceof \WP_Term && $st->parent ? [ (int) $st->term_id ] : [], TAX_STEWARD );
		}
		// Required: a name, a country, a state for the US, a city. Publishing without them is turned into a draft.
		$missing = [];
		if ( '' === trim( $post->post_title ) ) {
			$missing[] = 'a name';
		}
		if ( 'us' === $country && '' === $state ) {
			$missing[] = 'a state';
		}
		if ( '' === $meta['rp_city'] ) {
			$missing[] = 'a city';
		}
		if ( $missing && in_array( $post->post_status, [ 'publish', 'future' ], true ) ) {
			remove_action( 'save_post_' . POST_PARK, [ __CLASS__, 'save' ], 10 );
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
			add_action( 'save_post_' . POST_PARK, [ __CLASS__, 'save' ], 10, 2 );
			set_transient( 'rp_park_missing_' . get_current_user_id(), $missing, 120 );
		}
	}

	public static function redirect( string $location, int $post_id ): string {
		if ( POST_PARK === get_post_type( $post_id ) && get_transient( 'rp_park_missing_' . get_current_user_id() ) ) {
			$location = add_query_arg( 'rp_missing', '1', remove_query_arg( 'message', $location ) );
		}
		return $location;
	}

	public static function notices(): void {
		if ( empty( $_GET['rp_missing'] ) ) {
			return;
		}
		$missing = get_transient( 'rp_park_missing_' . get_current_user_id() );
		delete_transient( 'rp_park_missing_' . get_current_user_id() );
		if ( $missing ) {
			echo '<div class="notice notice-error"><p><strong>Saved as a draft.</strong> A park needs ' . esc_html( implode( ', ', $missing ) ) . ' before it can be published.</p></div>';
		}
	}

	/** Country codes and names from the place terms, United States first. */
	private static function countries(): array {
		$out   = [ 'us' => 'United States' ];
		$terms = get_terms( [ 'taxonomy' => TAX_PLACE, 'hide_empty' => false, 'parent' => 0, 'meta_key' => 'rp_level', 'meta_value' => 'country', 'orderby' => 'name' ] );
		foreach ( is_array( $terms ) ? $terms : [] as $t ) {
			$code = strtolower( (string) get_term_meta( $t->term_id, 'rp_code', true ) ) ?: $t->slug;
			if ( 'us' !== $code && ! isset( $out[ $code ] ) ) {
				$out[ $code ] = $t->name;
			}
		}
		return $out;
	}
}
