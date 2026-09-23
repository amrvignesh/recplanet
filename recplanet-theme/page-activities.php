<?php
/**
 * Template Name: Activities
 * The 44 activities, with counts.
 */
get_header();
$acts = get_terms( [ 'taxonomy' => 'rp_activity', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 100 ] );
$facs = get_terms( [ 'taxonomy' => 'rp_facility', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 60 ] );
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Activities</div>
      <h1>What do you want to do?</h1>
      <p class="lead" style="margin-top:14px">Every place on file is tagged with what you can do there, checked in person. Pick one, then narrow it to a state or a city.</p>
    </div>
  </div>
  <div class="grid3" style="margin-bottom:40px">
    <?php foreach ( $acts as $a ) : ?>
      <a class="box" href="<?php echo esc_url( get_term_link( $a ) ); ?>"><b><?php echo esc_html( $a->name ); ?></b><span><?php echo esc_html( number_format( $a->count ) ); ?> places</span></a>
    <?php endforeach; ?>
  </div>
  <?php if ( $facs ) : ?>
  <h2 class="h26">Facilities</h2>
  <div class="chips" style="margin-bottom:48px">
    <?php foreach ( $facs as $f ) : ?><a class="chip" href="<?php echo esc_url( get_term_link( $f ) ); ?>"><?php echo esc_html( ucfirst( $f->name ) ); ?> <span class="n"><?php echo esc_html( number_format( $f->count ) ); ?></span></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
