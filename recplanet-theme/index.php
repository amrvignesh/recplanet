<?php
/** Blog list, search results, and any archive without its own template. */
get_header();
$is_search = is_search();
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow"><?php echo $is_search ? 'Search' : 'Blog'; ?></div>
      <h1><?php echo $is_search ? 'Results for “' . esc_html( get_search_query() ) . '”' : ( is_home() ? 'From the trail notebook' : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
      <?php if ( $is_search ) : ?>
        <?php $hits = rp_index_rows( 'title LIKE %s OR city LIKE %s', [ '%' . $GLOBALS['wpdb']->esc_like( get_search_query() ) . '%', '%' . $GLOBALS['wpdb']->esc_like( get_search_query() ) . '%' ], 'acres DESC', 20 ); ?>
        <?php if ( $hits ) : ?>
          <h2 class="h26" style="margin-top:20px">Places</h2>
          <div class="plist"><?php foreach ( $hits as $r ) { echo rp_park_row( $r ); } ?></div>
        <?php endif; ?>
        <?php $places = get_terms( [ 'taxonomy' => 'rp_place', 'name__like' => get_search_query(), 'hide_empty' => true, 'number' => 12 ] ); if ( $places ) : ?>
          <h2 class="h26" style="margin-top:20px">Cities and states</h2>
          <div class="chips"><?php foreach ( $places as $p ) : ?><a class="chip" href="<?php echo esc_url( get_term_link( $p ) ); ?>"><?php echo esc_html( $p->name ); ?> <span class="n"><?php echo esc_html( number_format( $p->count ) ); ?></span></a><?php endforeach; ?></div>
        <?php endif; ?>
        <?php $acts = get_terms( [ 'taxonomy' => 'rp_activity', 'name__like' => get_search_query(), 'hide_empty' => true ] ); if ( $acts ) : ?>
          <h2 class="h26" style="margin-top:20px">Activities</h2>
          <div class="chips"><?php foreach ( $acts as $p ) : ?><a class="chip" href="<?php echo esc_url( get_term_link( $p ) ); ?>"><?php echo esc_html( $p->name ); ?></a><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ( have_posts() ) : ?><h2 class="h26" style="margin-top:20px">Posts and photos</h2><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if ( have_posts() ) : ?>
  <div class="posts" style="margin-bottom:40px">
    <?php while ( have_posts() ) : the_post(); ?>
      <a class="post" href="<?php the_permalink(); ?>"><time><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time><h3><?php the_title(); ?></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 24 ) ); ?></p></a>
    <?php endwhile; ?>
  </div>
  <nav class="pager" aria-label="Pages"><?php previous_posts_link( '← Newer' ); ?><?php next_posts_link( 'Older →' ); ?></nav>
  <?php elseif ( ! $is_search ) : ?>
  <p class="ph" style="margin-bottom:40px">Nothing here yet.</p>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
