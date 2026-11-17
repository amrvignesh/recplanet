<?php
/**
 * Template Name: World Parks
 * The map of everything on file: a live, zoomable map of every place (assets/js/world-map.js), then the United States
 * state by state, then the rest of the world.
 */
get_header();
global $wpdb;
$world = RP\Counter::get( 'world', 'world' );
$us    = RP\Counter::get( 'country', 'us' );
$idx   = RP\table( 'park_index' );

// State figures for the choropleth, from the roll-ups.
$rows = $wpdb->get_results( "SELECT scope_key, park_count, acres FROM " . RP\table( 'acre_rollup' ) . " WHERE scope = 'state' AND scope_key LIKE 'us/%'" );
$states = [];
$max_count = 1;
foreach ( $rows as $r ) {
	$code = substr( $r->scope_key, 3 );
	if ( ! isset( RP\us_states()[ $code ] ) ) {
		continue;
	}
	$states[ $code ] = [ 'name' => RP\us_states()[ $code ], 'count' => (int) $r->park_count, 'acres' => (float) $r->acres, 'url' => home_url( '/' . strtolower( $code ) . '/' ) ];
	$max_count = max( $max_count, (int) $r->park_count );
}
$top = $states;
uasort( $top, fn( $a, $b ) => $b['count'] <=> $a['count'] );

// Country centroids for the world map, computed from the records themselves.
$countries = $wpdb->get_results( "SELECT country, COUNT(*) n, COALESCE(SUM(acres),0) a, AVG(lat) lat, AVG(lng) lng FROM $idx WHERE country <> '' AND lat IS NOT NULL AND lat BETWEEN -85 AND 85 GROUP BY country ORDER BY n DESC" );
$names = [];
foreach ( get_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => false, 'meta_key' => 'rp_level', 'meta_value' => 'country', 'number' => 300 ] ) as $t ) {
	// Keyed by the country code, not the slug: Canada's slug cannot be "ca" once California has it.
	$code = strtolower( (string) get_term_meta( $t->term_id, 'rp_code', true ) ) ?: $t->slug;
	$names[ $code ] = [ 'name' => $t->name, 'url' => 'us' === $code ? home_url( '/states/' ) : get_term_link( $t ) ];
}
// The live map, zoomed out: one bubble per country, at the middle of its places, carrying its true count; a little
// closer the United States bubble gives way to one per state. k: c = a country, u = the United States, s = a state.
$bubbles = [];
foreach ( $countries as $c ) {
	$bubbles[] = [ 'k' => 'us' === $c->country ? 'u' : 'c', 'n' => $names[ $c->country ]['name'] ?? strtoupper( $c->country ), 'c' => (int) $c->n, 'a' => RP\format_acres( (float) $c->a, 0 ), 'lat' => round( (float) $c->lat, 3 ), 'lng' => round( (float) $c->lng, 3 ) ];
}
foreach ( $wpdb->get_results( "SELECT state, AVG(lat) lat, AVG(lng) lng FROM $idx WHERE country = 'us' AND state <> '' AND lat IS NOT NULL AND lat BETWEEN -85 AND 85 GROUP BY state" ) as $s ) {
	if ( isset( $states[ $s->state ] ) ) {
		$bubbles[] = [ 'k' => 's', 'n' => $states[ $s->state ]['name'], 'c' => $states[ $s->state ]['count'], 'a' => RP\format_acres( $states[ $s->state ]['acres'], 0 ), 'lat' => round( (float) $s->lat, 3 ), 'lng' => round( (float) $s->lng, 3 ) ];
	}
}
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">World Parks</div>
      <h1>Every place on file, on one map</h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( $world['count'] ) ); ?> places and <?php echo esc_html( RP\format_acres( $world['acres'], 0 ) ); ?> acres, entered by hand since 2008. The United States is covered city by city; the rest of the world is the national parks, reserves and wild places the founder has walked or studied. Zoom in to see every place, or tap a state or a country below.</p>
    </div>
  </div>
  <div class="worldlive">
    <div id="worldLive" data-bubbles='<?php echo esc_attr( wp_json_encode( $bubbles ) ); ?>'></div>
    <a class="btn btn-white open" id="worldAtlas" href="<?php echo esc_url( home_url( '/atlas/' ) ); ?>">Open this view in the atlas</a>
  </div>
  <p class="note" style="margin-top:10px">Zoom with the + and − buttons, by holding Ctrl and turning the mouse wheel, or with two fingers. A number is how many places are on file there; zoom in and each dot is one place, which opens when tapped. Map data © OpenStreetMap contributors, tiles by OpenFreeMap.</p>
</div>

<section class="usmap-wrap" id="united-states">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">United States</div><h2><?php echo esc_html( number_format( $us['count'] ) ); ?> places, state by state</h2></div>
      <p>Darker green means more places on file. Hover for the figures, tap to open the state.</p>
    </div>
    <div class="usmap-grid">
      <div class="usmap-box" id="usmapBox" data-states='<?php echo esc_attr( wp_json_encode( $states ) ); ?>' data-max="<?php echo (int) $max_count; ?>">
        <?php echo file_get_contents( RP_THEME_DIR . '/assets/img/us-states.svg' ); ?>
        <div class="maptip" id="usTip" hidden></div>
        <div class="maplegend"><span>fewer</span><i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><i class="l5"></i><span>more places</span></div>
      </div>
      <div class="side">
        <div class="box"><h4>Most places on file</h4>
          <?php foreach ( array_slice( $top, 0, 10, true ) as $code => $s ) : ?><a class="row" href="<?php echo esc_url( $s['url'] ); ?>"><span><?php echo esc_html( $s['name'] ); ?></span><b class="num"><?php echo esc_html( number_format( $s['count'] ) ); ?></b></a><?php endforeach; ?>
          <a class="more" href="<?php echo esc_url( home_url( '/states/' ) ); ?>">Every state, A to Z →</a>
        </div>
        <div class="box dark"><h4>Most acres</h4>
          <?php $by_acres = $states; uasort( $by_acres, fn( $a, $b ) => $b['acres'] <=> $a['acres'] ); foreach ( array_slice( $by_acres, 0, 5, true ) as $code => $s ) : ?><div class="kv"><span><a href="<?php echo esc_url( $s['url'] ); ?>" style="color:#fff;text-decoration:none"><?php echo esc_html( $s['name'] ); ?></a></span><b><?php echo esc_html( RP\format_acres( $s['acres'], 0 ) ); ?></b></div><?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="worldmap-wrap" id="rest-of-world">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">Rest of the world</div><h2><?php echo esc_html( count( $countries ) - 1 ); ?> more countries</h2></div>
      <p>Every country with places on file, largest collection first. Every one was added by hand.</p>
    </div>
    <div class="grid3" style="margin-bottom:48px">
      <?php foreach ( $countries as $c ) : if ( 'us' === $c->country ) continue; $nm = $names[ $c->country ]['name'] ?? strtoupper( $c->country ); $url = $names[ $c->country ]['url'] ?? '#'; ?>
        <a class="box" href="<?php echo esc_url( $url ); ?>"><b><?php echo esc_html( $nm ); ?></b><span><?php echo esc_html( number_format( (int) $c->n ) ); ?> places · <?php echo esc_html( RP\format_acres( (float) $c->a, 0 ) ); ?> acres</span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php get_footer(); ?>
