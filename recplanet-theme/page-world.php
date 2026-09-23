<?php
/**
 * Template Name: World Parks
 * The map of everything on file: the United States, state by state, then the rest of the world.
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
	$names[ $t->slug ] = [ 'name' => $t->name, 'url' => 'us' === $t->slug ? home_url( '/states/' ) : get_term_link( $t ) ];
}
$W = 1000; $H = 500;
$proj = fn( $lat, $lng ) => [ ( $lng + 180 ) / 360 * $W, ( 90 - $lat ) / 180 * $H ];
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">World Parks</div>
      <h1>Every place on file, on one map</h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( $world['count'] ) ); ?> places and <?php echo esc_html( RP\format_acres( $world['acres'], 0 ) ); ?> acres, entered by hand since 2008. The United States is covered city by city; the rest of the world is the national parks, reserves and wild places the founder has walked or studied. Tap a state or a country.</p>
    </div>
  </div>
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
      <p>Each dot sits at the centre of a country's places on file, sized by how many there are. Every one was added by hand.</p>
    </div>
    <div class="worldmap" id="worldMap">
      <svg viewBox="0 0 <?php echo (int) $W; ?> <?php echo (int) $H; ?>" role="img" aria-label="Countries with places on file, plotted by position">
        <rect width="<?php echo (int) $W; ?>" height="<?php echo (int) $H; ?>" fill="#14211A" rx="18"/>
        <g stroke="rgba(255,255,255,.06)" stroke-width="1">
          <?php for ( $la = -60; $la <= 80; $la += 20 ) { [ , $y ] = $proj( $la, 0 ); echo '<line x1="0" x2="' . (int) $W . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '"/>'; } ?>
          <?php for ( $lo = -150; $lo <= 180; $lo += 30 ) { [ $x ] = $proj( 0, $lo ); echo '<line y1="0" y2="' . (int) $H . '" x1="' . round( $x, 1 ) . '" x2="' . round( $x, 1 ) . '"/>'; } ?>
        </g>
        <?php foreach ( $countries as $c ) : [ $x, $y ] = $proj( (float) $c->lat, (float) $c->lng ); $r = 4 + min( 26, sqrt( (int) $c->n ) * 1.6 ); $nm = $names[ $c->country ]['name'] ?? strtoupper( $c->country ); $url = $names[ $c->country ]['url'] ?? '#'; ?>
          <a href="<?php echo esc_url( $url ); ?>" class="wdot" data-name="<?php echo esc_attr( $nm ); ?>" data-n="<?php echo (int) $c->n; ?>" data-a="<?php echo esc_attr( RP\format_acres( (float) $c->a, 0 ) ); ?>">
            <circle cx="<?php echo round( $x, 1 ); ?>" cy="<?php echo round( $y, 1 ); ?>" r="<?php echo round( $r + 6, 1 ); ?>" fill="#88B500" opacity=".18"/>
            <circle cx="<?php echo round( $x, 1 ); ?>" cy="<?php echo round( $y, 1 ); ?>" r="<?php echo round( $r, 1 ); ?>" fill="<?php echo 'us' === $c->country ? '#E85305' : '#88B500'; ?>" stroke="#F5F1E6" stroke-width="1.5"/>
            <?php if ( (int) $c->n >= 10 ) : ?><text x="<?php echo round( $x + $r + 6, 1 ); ?>" y="<?php echo round( $y + 4, 1 ); ?>" fill="#F5F1E6" font-size="12" font-family="Bricolage Grotesque, sans-serif" font-weight="700"><?php echo esc_html( $nm ); ?></text><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </svg>
      <div class="maptip" id="worldTip" hidden></div>
    </div>
    <div class="grid3" style="margin-top:22px;margin-bottom:48px">
      <?php foreach ( $countries as $c ) : if ( 'us' === $c->country ) continue; $nm = $names[ $c->country ]['name'] ?? strtoupper( $c->country ); $url = $names[ $c->country ]['url'] ?? '#'; ?>
        <a class="box" href="<?php echo esc_url( $url ); ?>"><b><?php echo esc_html( $nm ); ?></b><span><?php echo esc_html( number_format( (int) $c->n ) ); ?> places · <?php echo esc_html( RP\format_acres( (float) $c->a, 0 ) ); ?> acres</span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php get_footer(); ?>
