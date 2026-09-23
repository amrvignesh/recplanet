<?php
/**
 * Template Name: Atlas
 * The map of everything: MapLibre GL over OpenFreeMap tiles, data from the plugin's index.
 */
get_header();
$world = RP\Counter::get( 'world', 'world' );
$start = [ 'lat' => 39.5, 'lng' => -98.35, 'zoom' => 3.6 ];
if ( ! empty( $_GET['lat'] ) && ! empty( $_GET['lng'] ) ) {
	$start = [ 'lat' => (float) $_GET['lat'], 'lng' => (float) $_GET['lng'], 'zoom' => 13 ];
} elseif ( ! empty( $_GET['place'] ) ) {
	$t = get_term( (int) $_GET['place'], 'rp_place' );
	if ( $t && ! is_wp_error( $t ) ) {
		[ $w, $a ] = rp_place_where( $t );
		$b = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT MIN(lat) a, MAX(lat) b, MIN(lng) c, MAX(lng) d FROM " . RP\table( 'park_index' ) . " WHERE ($w) AND lat IS NOT NULL", $a ) );
		if ( $b && $b->a ) {
			$start = [ 'bbox' => [ (float) $b->c, (float) $b->a, (float) $b->d, (float) $b->b ] ];
		}
	}
}
$activity = sanitize_text_field( $_GET['activity'] ?? '' );
?>
<div class="atlas2" id="atlas" data-start='<?php echo esc_attr( wp_json_encode( $start ) ); ?>' data-activity="<?php echo esc_attr( $activity ); ?>">
  <aside>
    <div><h4>Travel time from you</h4><div class="seg" id="travel"><button type="button" data-km="1.25" data-mode="walk" class="on">Walk 15</button><button type="button" data-km="3.75" data-mode="bike">Bike 15</button><button type="button" data-km="25" data-mode="drive">Drive 30</button></div><p class="note" style="margin-top:6px">Press <b>Put me here</b> on the map, then tap a spot, or use <b>Locate me</b>.</p></div>
    <div><h4>Activity lens</h4><div class="chips" id="lens"><button class="chip on" type="button" data-a="">Any</button>
      <?php foreach ( [ 'Playground', 'Fishing', 'Hiking', 'Camping', 'Swimming', 'Disc-Golf', 'Dog-Park', 'Kayaking', 'Skate Park', 'Basketball', 'Picnicking', 'Biking' ] as $a ) : ?><button class="chip" type="button" data-a="<?php echo esc_attr( $a ); ?>"><?php echo esc_html( $a ); ?></button><?php endforeach; ?>
      <select id="lensMore" aria-label="More activities"><option value="">More…</option><?php foreach ( RP\ACTIVITIES as $a ) : ?><option value="<?php echo esc_attr( $a ); ?>"><?php echo esc_html( $a ); ?></option><?php endforeach; ?></select>
    </div></div>
    <div><h4>Managed by</h4>
      <?php foreach ( RP\STEWARD_LEVELS as $k => $lbl ) : ?><label class="ck"><input type="checkbox" class="stw" value="<?php echo esc_attr( $k ); ?>" checked><?php echo esc_html( $lbl ); ?></label><?php endforeach; ?>
    </div>
    <div><h4>Size</h4><label for="minac" class="visually-hidden">Minimum acres</label><input id="minac" type="range" min="0" max="100" value="0" style="width:100%;accent-color:var(--orange)"><div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted)"><span>Any size</span><span id="minacLbl">0 acres and up</span></div></div>
    <div><h4>Contribute mode</h4><label class="ck"><input type="checkbox" id="nophoto">Only places with no photo yet</label><p class="note">See where a photo would fill a gap. Every one is a contest entry waiting to happen.</p></div>
    <p class="note">Map data © OpenStreetMap contributors, tiles by OpenFreeMap. Every dot is a RecPlanet record.</p>
  </aside>
  <div class="mapwrap" id="mapwrap">
    <div id="map" style="position:absolute;inset:0"></div>
    <div class="modes" id="modes"><button type="button" data-m="dots" class="on">Places</button><button type="button" data-m="acres">Acres</button><button type="button" data-m="steward">Managed by</button></div>
    <div class="tools"><button type="button" id="locateMe">◎ Locate me</button><button type="button" id="placeMe">✥ Put me here</button><button type="button" id="selArea">▭ Select an area</button><button type="button" id="shareView">⇪ Copy link to this view</button></div>
    <div class="legend" id="legend"></div>
    <div class="timebar"><button type="button" id="play">▶ Watch it grow</button><small>2010</small><input type="range" id="year" min="2010" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" aria-label="Show places added up to this year"><small><?php echo esc_html( gmdate( 'Y' ) ); ?></small><b id="yearLbl"><?php echo esc_html( gmdate( 'Y' ) ); ?></b></div>
  </div>
  <div class="rp" id="rp">
    <div class="hd"><span class="eyebrow" id="rpTitle">In view</span><div class="big"><div><b id="vN">0</b><small>places</small></div><div><b id="vA" style="color:var(--orange)">0</b><small>acres</small></div></div><small class="note" id="rpNote">Pan and zoom to change what is counted. Click a cluster to open it.</small></div>
    <div id="rpBody"><div class="empty">Zoom in, or place yourself on the map, to list places.</div></div>
  </div>
</div>
<?php get_footer(); ?>
