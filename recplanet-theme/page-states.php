<?php
/**
 * Template Name: States
 * Every US state and territory on file, with counts and acres.
 */
get_header();
$us_term = get_terms( [ 'taxonomy' => 'rp_place', 'slug' => 'us', 'parent' => 0, 'hide_empty' => false, 'number' => 1 ] );
$states  = $us_term ? get_terms( [ 'taxonomy' => 'rp_place', 'parent' => $us_term[0]->term_id, 'hide_empty' => true, 'orderby' => 'name', 'number' => 100 ] ) : [];
$us = RP\Counter::get( 'country', 'us' );
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">United States</div>
      <h1>Every state, city by city</h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( $us['count'] ) ); ?> places across <?php echo esc_html( RP\format_acres( $us['acres'], 0 ) ); ?> acres, from national forests to neighbourhood playgrounds. Pick a state, then a city.</p>
    </div>
  </div>
  <div class="grid3" style="margin-bottom:48px">
    <?php foreach ( $states as $st ) : $s = rp_place_stats( $st ); ?>
      <a class="box" href="<?php echo esc_url( get_term_link( $st ) ); ?>"><b><?php echo esc_html( $st->name ); ?></b><span><?php echo esc_html( number_format( $s['count'] ) ); ?> places · <?php echo esc_html( RP\format_acres( $s['acres'], 0 ) ); ?> acres</span></a>
    <?php endforeach; ?>
  </div>
</div>
<?php get_footer(); ?>
