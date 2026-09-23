<?php
/**
 * Template Name: Blog Tags
 * Every tag used on the blog and on photos, sized by use, as the old site's Blog Tags page.
 */
get_header();
$tags = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 400 ] );
$max  = $tags ? max( array_map( fn( $t ) => $t->count, $tags ) ) : 1;
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Blog Tags</div>
      <h1>Every subject the blog has touched</h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( count( $tags ) ) ); ?> tags across the posts and the contest photos. Bigger means used more often.</p>
    </div>
  </div>
  <div class="tagcloud" style="margin-bottom:48px">
    <?php foreach ( $tags as $t ) : $size = 13 + round( 17 * sqrt( $t->count / max( 1, $max ) ) ); ?>
      <a class="chip" href="<?php echo esc_url( get_tag_link( $t ) ); ?>" style="font-size:<?php echo (int) $size; ?>px"><?php echo esc_html( $t->name ); ?> <span class="n"><?php echo esc_html( number_format( $t->count ) ); ?></span></a>
    <?php endforeach; ?>
    <?php if ( ! $tags ) : ?><p class="ph">No tags yet. They arrive with the blog import.</p><?php endif; ?>
  </div>
</div>
<?php get_footer(); ?>
