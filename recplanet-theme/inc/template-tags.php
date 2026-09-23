<?php
/**
 * Small output helpers used by the templates.
 */

defined( 'ABSPATH' ) || exit;

function rp_logo( string $class = '' ): string {
	return '<picture><source type="image/webp" srcset="' . esc_url( RP_THEME_URI . '/assets/img/logo.webp' ) . '">'
		. '<img class="' . esc_attr( $class ) . '" src="' . esc_url( RP_THEME_URI . '/assets/img/logo.png' ) . '" alt="RecPlanet" width="297" height="98" fetchpriority="high" decoding="async"></picture>';
}

/** First sentence or two of a park's description, plain text. */
function rp_park_lead( int $id, int $chars = 220 ): string {
	$text = wp_strip_all_tags( get_post_field( 'post_content', $id ) );
	$text = trim( preg_replace( '/\s+/', ' ', $text ) );
	if ( '' === $text ) {
		$city  = get_post_meta( $id, 'rp_city', true );
		$state = RP\us_states()[ get_post_meta( $id, 'rp_state', true ) ] ?? get_post_meta( $id, 'rp_state', true );
		$acres = get_post_meta( $id, 'rp_acreage', true );
		return trim( get_the_title( $id ) . ( $city ? " in $city" : '' ) . ( $state ? ", $state" : '' ) . ( '' !== $acres ? ', ' . RP\format_acres( (float) $acres ) . ' acres' : '' ) . '.' );
	}
	return mb_strlen( $text ) > $chars ? mb_substr( $text, 0, $chars - 1 ) . '…' : $text;
}

/** "City · State" line for a park. */
function rp_park_place_line( int $id ): string {
	$city    = get_post_meta( $id, 'rp_city', true );
	$state   = get_post_meta( $id, 'rp_state', true );
	$country = get_post_meta( $id, 'rp_country', true ) ?: 'us';
	$sn      = 'us' === $country ? ( RP\us_states()[ $state ] ?? $state ) : $state;
	$parts   = array_filter( [ $city, $sn, 'us' === $country ? '' : strtoupper( $country ) ] );
	return implode( ', ', $parts );
}

/** Activity chips for a park or a list of names. */
function rp_activity_chips( array $names, string $link_base = '' ): string {
	$out = '';
	foreach ( $names as $n ) {
		$t = get_term_by( 'name', $n, 'rp_activity' );
		$href = $t ? get_term_link( $t ) : '#';
		$out .= '<a class="chip" href="' . esc_url( $href ) . '">' . esc_html( $n ) . '</a>';
	}
	return $out;
}

/**
 * The acre ribbon: World > country > state > county > city > (this place). Log-scaled bars.
 * $rows: list of [label, acres, url, highlight]. World is added automatically.
 */
function rp_acre_ribbon( array $rows, bool $compact = false ): string {
	$world = RP\Counter::get( 'world', 'world' );
	array_unshift( $rows, [ 'World', $world['acres'], home_url( '/' ), false ] );
	$max  = log10( max( 10, $world['acres'] ) );
	$html = '<div class="ribbon' . ( $compact ? ' compact' : '' ) . '">';
	foreach ( $rows as $i => [ $label, $acres, $url, $hl ] ) {
		$w   = $acres > 0 ? max( 3, round( log10( max( 1, $acres ) ) / $max * 100 ) ) : 3;
		$val = null === $acres ? '<span class="ph">not recorded</span>' : RP\format_acres( $acres );
		$html .= sprintf(
			'<a class="rrow%s" href="%s"><span class="name ind%d">%s</span>%s<span class="val">%s</span></a>',
			$hl ? ' hl' : '', esc_url( $url ), min( 4, $i ), esc_html( $label ),
			$compact ? '' : '<span class="track"><span class="fill" style="--w:' . $w . '%"></span></span>',
			$val
		);
	}
	return $html . '</div>';
}

/** Ribbon rows for a park, from its place chain. */
function rp_park_ribbon( int $id ): string {
	$rows  = [];
	$c     = get_post_meta( $id, 'rp_country', true ) ?: 'us';
	$s     = get_post_meta( $id, 'rp_state', true );
	$city  = get_post_meta( $id, 'rp_city', true );
	$place = rp_park_place_term( $id );
	$chain = $place ? rp_place_chain( $place ) : [ 'terms' => [] ];
	$cs    = RP\Counter::get( 'country', $c );
	$rows[] = [ isset( $chain['terms']['country'] ) ? $chain['terms']['country']->name : strtoupper( $c ), $cs['acres'], isset( $chain['terms']['country'] ) ? get_term_link( $chain['terms']['country'] ) : home_url( '/' ), false ];
	if ( $s ) {
		$ss = RP\Counter::get( 'state', "$c/$s" );
		$rows[] = [ ( RP\us_states()[ $s ] ?? $s ) . ' · ' . number_format( $ss['count'] ) . ' places', $ss['acres'], isset( $chain['terms']['state'] ) ? get_term_link( $chain['terms']['state'] ) : home_url( '/' . strtolower( $s ) ), false ];
	}
	if ( $s && $city ) {
		$cy = RP\Counter::get( 'city', "$c/$s/" . RP\legacy_slug( $city ) );
		$rows[] = [ $city . ' · ' . number_format( $cy['count'] ) . ' places', $cy['acres'], isset( $chain['terms']['city'] ) ? get_term_link( $chain['terms']['city'] ) : home_url( '/' . strtolower( $s ) . '/' . RP\legacy_slug( $city ) ), false ];
	}
	$acres = get_post_meta( $id, 'rp_acreage', true );
	$rows[] = [ get_the_title( $id ), '' === $acres ? null : (float) $acres, get_permalink( $id ), true ];
	return rp_acre_ribbon( $rows );
}

/** Breadcrumb strip for a park or place. */
function rp_crumbs( array $items, string $right = '' ): string {
	$html = '<div class="crumbs"><div class="wrap">';
	$n    = count( $items );
	foreach ( $items as $i => [ $label, $url ] ) {
		$html .= $i === $n - 1 ? '<b>' . esc_html( $label ) . '</b>' : '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a><span>›</span>';
	}
	if ( $right ) {
		$html .= '<span class="share">' . esc_html( $right ) . '</span>';
	}
	return $html . '</div></div>';
}

/** Crumb items for a place term, from the country down. */
function rp_place_crumbs( WP_Term $t ): array {
	$items = [ [ 'World', home_url( '/' ) ] ];
	$chain = [];
	$cur   = $t;
	while ( $cur && ! is_wp_error( $cur ) ) {
		array_unshift( $chain, $cur );
		$cur = $cur->parent ? get_term( $cur->parent, 'rp_place' ) : null;
	}
	foreach ( $chain as $c ) {
		$items[] = [ $c->name, get_term_link( $c ) ];
	}
	return $items;
}

/** One row in a park list. Takes an index-table row. */
function rp_park_row( object $r ): string {
	$acts = [];
	foreach ( RP\ACTIVITIES as $i => $name ) {
		if ( ( (int) $r->activity_bits ) & ( 1 << $i ) ) {
			$acts[] = $name;
		}
	}
	$chips = '';
	foreach ( array_slice( $acts, 0, 4 ) as $a ) {
		$chips .= '<span>' . esc_html( $a ) . '</span>';
	}
	if ( count( $acts ) > 4 ) {
		$chips .= '<span class="more">+' . ( count( $acts ) - 4 ) . '</span>';
	}
	if ( ! $acts ) {
		$chips = '<span class="more">no activities recorded yet</span>';
	}
	$acres = null === $r->acres ? '<span class="ph">not recorded</span>' : RP\format_acres( (float) $r->acres );
	return '<a class="prow" href="' . esc_url( get_permalink( (int) $r->post_id ) ) . '"><span class="pname">' . esc_html( $r->title ) . ( $r->city ? '<small> · ' . esc_html( $r->city ) . '</small>' : '' ) . '</span><span class="pacts">' . $chips . '</span><span class="pac num">' . $acres . '</span></a>';
}

/** Card for the home page and lists. */
function rp_park_card( object $r, string $lead = '' ): string {
	$id   = (int) $r->post_id;
	$img  = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'rp-card', [ 'loading' => 'lazy' ] ) : rp_scene_svg( $id );
	$acts = [];
	foreach ( RP\ACTIVITIES as $i => $name ) {
		if ( ( (int) $r->activity_bits ) & ( 1 << $i ) ) {
			$acts[] = $name;
		}
	}
	$tags = '';
	foreach ( array_slice( $acts, 0, 3 ) as $a ) {
		$tags .= '<span>' . esc_html( $a ) . '</span>';
	}
	if ( count( $acts ) > 3 ) {
		$tags .= '<span>+' . ( count( $acts ) - 3 ) . '</span>';
	}
	if ( ! $acts ) {
		$tags = '<span>No activities recorded yet</span>';
	}
	$acres = null === $r->acres ? 'acres not recorded' : RP\format_acres( (float) $r->acres ) . ' acres';
	return '<a class="card" href="' . esc_url( get_permalink( $id ) ) . '"><div class="scene">' . $img . '</div><div class="card-body"><div class="card-meta"><span class="walk">' . esc_html( $lead ?: ( $r->city ?: RP\us_states()[ $r->state ] ?? $r->state ) ) . '</span><span class="ac">' . esc_html( $acres ) . '</span></div><h3>' . esc_html( $r->title ) . '</h3><p>' . esc_html( rp_park_lead( $id, 120 ) ) . '</p><div class="tags">' . $tags . '</div></div></a>';
}

/** An illustrated stand-in for parks without a photo, varied by id so a grid isn't uniform. */
function rp_scene_svg( int $seed ): string {
	$v = $seed % 3;
	$skies = [ [ '#22CBEF', '#D9F3FB' ], [ '#FFB36B', '#FFE9C2' ], [ '#0FB8E6', '#BDEBF7' ] ];
	[ $a, $b ] = $skies[ $v ];
	$sun = $v === 1 ? '<circle cx="90" cy="70" r="26" fill="#FF8A3D" opacity=".9"/>' : '<circle cx="250" cy="48" r="22" fill="#FFE66D" opacity=".9"/>';
	return '<svg viewBox="0 0 320 180" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><linearGradient id="s' . $seed . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient></defs><rect width="320" height="180" fill="url(#s' . $seed . ')"/>' . $sun . '<path fill="#7DBF3F" d="M0 120 C 60 90, 120 92, 180 110 S 280 130, 320 105 V180 H0Z"/><path fill="#2F6B3A" d="M0 150 C 80 130, 160 140, 240 150 S 300 160, 320 150 V180 H0Z"/></svg>';
}

/** Google Maps Embed for one place. Free and unlimited. */
function rp_embed_map( float $lat, float $lng, string $title, string $mode = 'place', int $zoom = 15 ): string {
	$key = RP\Settings::maps_key();
	if ( ! $key ) {
		return '<div class="pmap"><div style="padding:24px;color:#C6D2C0">Add the Google Maps Embed key under Settings › RecPlanet to show the map.</div></div>';
	}
	if ( 'streetview' === $mode ) {
		$src = sprintf( 'https://www.google.com/maps/embed/v1/streetview?key=%s&location=%F,%F&heading=0&pitch=0&fov=90', rawurlencode( $key ), $lat, $lng );
	} elseif ( 'directions' === $mode ) {
		$src = sprintf( 'https://www.google.com/maps/embed/v1/directions?key=%s&destination=%F,%F&origin=%s', rawurlencode( $key ), $lat, $lng, rawurlencode( 'Current Location' ) );
	} else {
		$src = sprintf( 'https://www.google.com/maps/embed/v1/place?key=%s&q=%F,%F&zoom=%d', rawurlencode( $key ), $lat, $lng, $zoom );
	}
	return '<iframe class="gmap" src="' . esc_url( $src ) . '" title="' . esc_attr( $title ) . '" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" style="border:0;width:100%;height:100%"></iframe>';
}

/** Google Maps link for directions, no API needed. */
function rp_directions_url( float $lat, float $lng ): string {
	return sprintf( 'https://www.google.com/maps/dir/?api=1&destination=%F,%F', $lat, $lng );
}

/** Star rating widget for a photo. Filled by main.js from the REST status. */
function rp_stars( int $photo_id ): string {
	$votes = (int) get_post_meta( $photo_id, 'rp_votes', true );
	$score = (float) get_post_meta( $photo_id, 'rp_score', true );
	$html  = '<div class="stars" data-photo="' . $photo_id . '" data-score="' . esc_attr( $score ) . '" role="group" aria-label="Rate this photo">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$html .= '<button type="button" class="star' . ( $score >= $i - 0.25 ? ' on' : '' ) . '" data-v="' . $i . '" aria-label="' . $i . ' star' . ( $i > 1 ? 's' : '' ) . '">★</button>';
	}
	return $html . '<span class="cnt"><b class="num">' . esc_html( number_format( $score, 1 ) ) . '</b> from <b class="num">' . esc_html( number_format( $votes ) ) . '</b> ratings</span></div>';
}

/** "Fishing" -> "fish", "Playground" -> "find a playground", for page titles. */
function rp_activity_verb( string $name ): string {
	$map = [
		'Playground' => 'find a playground', 'Picnicking' => 'picnic', 'Basketball' => 'play basketball', 'Restrooms' => 'find restrooms',
		'Baseball' => 'play baseball', 'Hiking' => 'hike', 'Softball' => 'play softball', 'Soccer' => 'play soccer', 'Tennis' => 'play tennis',
		'Fishing' => 'fish', 'Walking' => 'walk', 'Biking' => 'bike', 'Swimming' => 'swim', 'Kayaking' => 'kayak', 'Canoeing' => 'canoe',
		'Volleyball' => 'play volleyball', 'Camping' => 'camp', 'Boating' => 'go boating', 'Sprayground' => 'find a sprayground',
		'Hunting' => 'hunt', 'Horse Back Riding' => 'ride horses', 'Dog-Park' => 'find a dog park', 'Horseshoes' => 'play horseshoes',
		'Cross-Country Skiing' => 'ski cross-country', 'Skate Park' => 'skate', 'Disc-Golf' => 'play disc golf', 'Golf' => 'golf',
		'Water-Skiing' => 'water-ski', 'Handball' => 'play handball', 'Ice-Skating' => 'ice-skate', 'Roller-hockey' => 'play roller hockey',
		'Snowmobiling' => 'snowmobile', 'Sailboarding' => 'sailboard', 'Sailing' => 'sail', 'Backpacking' => 'backpack', 'Bocce' => 'play bocce',
		'Climbing' => 'climb', 'Archery Range' => 'shoot archery', 'Surfing' => 'surf', 'Scuba' => 'dive', 'Skiing' => 'ski', 'Rafting' => 'raft',
		'Caving' => 'go caving', 'Hang-Gliding' => 'hang-glide',
	];
	return $map[ $name ] ?? strtolower( $name );
}

/** /tx/page/2/?sort=name style pagination links. */
function rp_page_link( string $base, int $page, string $sort = 'acres' ): string {
	$url = trailingslashit( $base ) . ( $page > 1 ? 'page/' . $page . '/' : '' );
	return 'acres' === $sort || '' === $sort ? $url : add_query_arg( 'sort', $sort, $url );
}
