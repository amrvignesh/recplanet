<?php
/**
 * Template Name: World Parks
 * Every country on file, with counts and acres, the United States first.
 */
get_header();
$countries = get_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => true, 'meta_key' => 'rp_level', 'meta_value' => 'country', 'orderby' => 'count', 'order' => 'DESC', 'number' => 200 ] );
$world = RP\Counter::get( 'world', 'world' );
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">World Parks</div>
      <h1>Recreation and conservation land around the world</h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( $world['count'] ) ); ?> places across <?php echo esc_html( count( $countries ) ); ?> countries and <?php echo esc_html( RP\format_acres( $world['acres'], 0 ) ); ?> acres. The United States is covered city by city; the rest of the world is the national parks, reserves and wild places the founder has visited or studied, and it grows.</p>
    </div>
  </div>
  <div class="grid3" style="margin-bottom:48px">
    <?php foreach ( $countries as $c ) : $s = rp_place_stats( $c ); ?>
      <a class="box" href="<?php echo esc_url( 'us' === $c->slug ? home_url( '/states' ) : get_term_link( $c ) ); ?>"><b><?php echo esc_html( $c->name ); ?></b><span><?php echo esc_html( number_format( $s['count'] ) ); ?> places · <?php echo esc_html( RP\format_acres( $s['acres'], 0 ) ); ?> acres</span></a>
    <?php endforeach; ?>
  </div>
</div>
<?php get_footer(); ?>
