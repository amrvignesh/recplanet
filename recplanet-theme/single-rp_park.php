<?php
/** A park record. */
get_header();
the_post();
$id      = get_the_ID();
$acres   = get_post_meta( $id, 'rp_acreage', true );
$acres_f = '' === $acres ? null : (float) $acres;
$lat     = (float) get_post_meta( $id, 'rp_lat', true );
$lng     = (float) get_post_meta( $id, 'rp_lng', true );
$city    = get_post_meta( $id, 'rp_city', true );
$state   = get_post_meta( $id, 'rp_state', true );
$country = get_post_meta( $id, 'rp_country', true ) ?: 'us';
$street  = get_post_meta( $id, 'rp_street', true );
$postal  = get_post_meta( $id, 'rp_postal', true );
$web     = get_post_meta( $id, 'rp_website', true );
$verified = get_post_meta( $id, 'rp_verified_on', true );
$acts    = wp_get_object_terms( $id, 'rp_activity', [ 'fields' => 'names' ] );
$stew    = wp_get_object_terms( $id, 'rp_steward' );
$stew_name = ''; $stew_level = ''; $stew_link = '';
foreach ( $stew as $t ) { if ( $t->parent ) { $stew_name = $t->name; $stew_link = get_term_link( $t ); $stew_level = get_term( $t->parent )->name; } }
$place   = rp_park_place_term( $id );
$crumbs  = $place ? rp_place_crumbs( $place ) : [ [ 'World', home_url( '/' ) ] ];
$crumbs[] = [ get_the_title(), get_permalink() ];
$world   = RP\Counter::get( 'world', 'world' );
$photos  = rp_park_photos( $id );
$near    = ( $lat && $lng ) ? rp_index_rows( 'post_id <> %d AND lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f', [ $id, $lat - 0.05, $lat + 0.05, $lng - 0.06, $lng + 0.06 ], 'acres DESC', 6 ) : [];
$in_city = $city ? rp_index_rows( 'post_id <> %d AND country = %s AND state = %s AND city = %s', [ $id, $country, $state, $city ], 'acres DESC', 6 ) : [];
$city_stats = $city ? RP\Counter::get( 'city', "$country/$state/" . RP\legacy_slug( $city ) ) : null;
$city_term  = $place && 'city' === get_term_meta( $place->term_id, 'rp_level', true ) ? $place : null;
?>
<?php echo rp_crumbs( $crumbs, null === $acres_f ? '' : RP\format_acres( $acres_f ) . ' of ' . RP\format_acres( $world['acres'] ) . ' acres' ); ?>
<div class="wrap">
  <div class="phead">
    <div>
      <div class="tags" style="margin-bottom:14px">
        <?php if ( $stew_level ) : ?><span class="lvl"><?php echo esc_html( strtoupper( $stew_level ) ); ?></span><?php endif; ?>
        <?php foreach ( $acts as $a ) : $t = get_term_by( 'name', $a, 'rp_activity' ); ?><a href="<?php echo esc_url( $t ? get_term_link( $t ) : '#' ); ?>"><?php echo esc_html( $a ); ?></a><?php endforeach; ?>
        <?php if ( ! $acts ) : ?><span class="ph">Activities not yet recorded</span><?php endif; ?>
      </div>
      <h1><?php the_title(); ?></h1>
      <div class="placeline"><?php echo esc_html( rp_park_place_line( $id ) ); ?></div>
      <div class="lead entry"><?php the_content(); ?><?php if ( '' === trim( get_the_content() ) ) : ?><p class="ph">No description on file yet.</p><?php endif; ?></div>
      <?php if ( null !== $acres_f ) : ?>
        <div class="human"><b><?php echo esc_html( RP\format_acres( $acres_f ) ); ?> acres</b><span><?php echo esc_html( RP\Human_Scale::describe( $acres_f ) ); ?></span></div>
      <?php endif; ?>
      <div class="fresh" data-park="<?php echo (int) $id; ?>">
        <span class="q">Been here lately? Is this still accurate?</span>
        <button type="button" class="yes" data-answer="yes">Yes, still right</button>
        <button type="button" data-answer="no">Something changed</button>
        <span class="note">Last checked <?php echo esc_html( $verified ? wp_date( 'j F Y', strtotime( $verified ) ) : 'unknown' ); ?>. On file since <?php echo esc_html( get_the_date( 'j F Y' ) ); ?>.</span>
        <form class="fresh-form" hidden><label>What changed?<textarea name="note" rows="2" maxlength="2000"></textarea></label><button type="submit" class="btn btn-orange">Send</button></form>
      </div>
    </div>
    <div class="facts">
      <div><span class="k">Acreage</span><span class="v num"><?php echo null === $acres_f ? '<span class="ph">not recorded</span>' : esc_html( RP\format_acres( $acres_f ) ); ?></span></div>
      <div><span class="k">Managed by</span><span class="v"><?php echo $stew_name ? '<a href="' . esc_url( $stew_link ) . '">' . esc_html( $stew_name ) . '</a>' : '<span class="ph">not recorded</span>'; ?></span></div>
      <div class="wide"><span class="k">Address</span><span class="v"><?php echo esc_html( trim( implode( ', ', array_filter( [ $street, $city, trim( $state . ' ' . $postal ) ] ) ) ) ?: '—' ); ?></span></div>
      <div><span class="k">Coordinates</span><span class="v mono"><?php echo $lat ? esc_html( sprintf( '%.6f, %.6f', $lat, $lng ) ) : '<span class="ph">none</span>'; ?></span></div>
      <div><span class="k">Website</span><span class="v"><?php echo $web ? '<a href="' . esc_url( $web ) . '" rel="nofollow noopener" target="_blank">' . esc_html( wp_parse_url( $web, PHP_URL_HOST ) ?: $web ) . '</a>' : '<span class="ph">none on file</span>'; ?></span></div>
      <div class="wide actions">
        <?php if ( $lat ) : ?><a class="btn btn-lime" href="<?php echo esc_url( rp_directions_url( $lat, $lng ) ); ?>" target="_blank" rel="noopener">Directions</a><?php endif; ?>
        <a class="btn btn-ghost light" href="<?php echo esc_url( add_query_arg( 'park', $id, home_url( '/contest' ) ) ); ?>">Add a photo</a>
      </div>
    </div>
  </div>

  <?php if ( $lat && $lng ) : ?>
  <div class="maps">
    <div class="pmap"><?php echo rp_embed_map( $lat, $lng, get_the_title() . ' on the map' ); ?><a class="btn btn-white open" href="<?php echo esc_url( add_query_arg( [ 'lat' => $lat, 'lng' => $lng ], home_url( '/atlas' ) ) ); ?>">Open in atlas</a></div>
    <div class="pmap sv"><?php echo rp_embed_map( $lat, $lng, 'Street View near ' . get_the_title(), 'streetview' ); ?><span class="pill">Street View</span></div>
  </div>
  <?php endif; ?>

  <section class="two" style="padding-top:36px">
    <div class="stack">
      <div>
        <h2 class="h26">Photographs from the community</h2>
        <div class="gallery four">
          <?php foreach ( $photos as $p ) : ?>
            <a class="tile" href="<?php echo esc_url( get_permalink( $p ) ); ?>" style="--r:0;aspect-ratio:4/3"><?php echo has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'rp-card', [ 'loading' => 'lazy' ] ) : rp_scene_svg( $p->ID ); ?><div class="cap"><?php echo esc_html( get_the_title( $p ) ); ?></div></a>
          <?php endforeach; ?>
          <?php if ( ! $photos ) : ?><div class="tile empty">No photos yet</div><?php endif; ?>
          <a class="tile add" href="<?php echo esc_url( add_query_arg( 'park', $id, home_url( '/contest' ) ) ); ?>"><span>＋</span><span>Add yours</span></a>
        </div>
        <p class="note">Every upload here fills a gap and is entered in the running contest.</p>
      </div>
      <?php if ( comments_open() || get_comments_number() ) : ?>
      <div>
        <h2 class="h26">Notes from visitors</h2>
        <?php comments_template(); ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="side">
      <?php echo rp_park_ribbon( $id ); ?>
      <?php if ( $in_city ) : ?>
      <div class="box"><h4>Also in <?php echo esc_html( $city ); ?></h4>
        <?php foreach ( $in_city as $r ) : ?><a class="row" href="<?php echo esc_url( get_permalink( (int) $r->post_id ) ); ?>"><span><?php echo esc_html( $r->title ); ?></span><b class="num"><?php echo null === $r->acres ? '' : esc_html( RP\format_acres( (float) $r->acres ) ); ?></b></a><?php endforeach; ?>
        <?php if ( $city_term && $city_stats ) : ?><a class="more" href="<?php echo esc_url( get_term_link( $city_term ) ); ?>">All <?php echo esc_html( number_format( $city_stats['count'] ) ); ?> places in <?php echo esc_html( $city ); ?> →</a><?php endif; ?>
      </div>
      <?php elseif ( $near ) : ?>
      <div class="box"><h4>Nearby</h4>
        <?php foreach ( $near as $r ) : ?><a class="row" href="<?php echo esc_url( get_permalink( (int) $r->post_id ) ); ?>"><span><?php echo esc_html( $r->title ); ?></span><b class="num"><?php echo null === $r->acres ? '' : esc_html( RP\format_acres( (float) $r->acres ) ); ?></b></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="box dark"><h4>Record</h4>
        <div class="kv"><span>Added</span><b><?php echo esc_html( get_the_date( 'j M Y' ) ); ?></b></div>
        <div class="kv"><span>Last checked</span><b><?php echo esc_html( $verified ? wp_date( 'j M Y', strtotime( $verified ) ) : '—' ); ?></b></div>
        <div class="kv"><span>Address</span><b class="mono"><?php echo esc_html( wp_parse_url( get_permalink(), PHP_URL_PATH ) ); ?></b></div>
        <a href="#" class="more fresh-open">Suggest a correction →</a>
      </div>
    </div>
  </section>
</div>
<?php get_footer(); ?>
