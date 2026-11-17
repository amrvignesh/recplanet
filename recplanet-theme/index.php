<?php
/** Blog list, search results, and any archive without its own template. */
get_header();
$is_search = is_search();
if ( $is_search ) :
	// Search: places from the index, then matching cities, activities, posts and photos. ?per= sets how many show, ?pg= the page.
	global $wp_query;
	$rp_q = trim( (string) get_search_query( false ) );
	[ $rp_per, $rp_pg ] = rp_list_paging( 20 );
	$rp_like   = '%' . $GLOBALS['wpdb']->esc_like( $rp_q ) . '%';
	$rp_where  = 'title LIKE %s OR city LIKE %s';
	$rp_has_q  = '' !== $rp_q;
	$rp_n      = $rp_has_q ? rp_index_sum( $rp_where, [ $rp_like, $rp_like ] )['count'] : 0;
	$rp_hits   = $rp_n ? rp_index_rows( $rp_where, [ $rp_like, $rp_like ], 'acres DESC', $rp_per, ( $rp_pg - 1 ) * $rp_per ) : [];
	$rp_places = $rp_has_q ? get_terms( [ 'taxonomy' => 'rp_place', 'name__like' => $rp_q, 'hide_empty' => true, 'number' => 40, 'orderby' => 'count', 'order' => 'DESC' ] ) : [];
	$rp_places = is_array( $rp_places ) ? $rp_places : [];
	$rp_n_pl   = $rp_has_q ? (int) wp_count_terms( [ 'taxonomy' => 'rp_place', 'name__like' => $rp_q, 'hide_empty' => true ] ) : 0;
	$rp_acts   = $rp_has_q ? get_terms( [ 'taxonomy' => 'rp_activity', 'name__like' => $rp_q, 'hide_empty' => true ] ) : [];
	$rp_acts   = is_array( $rp_acts ) ? $rp_acts : [];
	$rp_n_po   = $rp_has_q ? (int) $wp_query->found_posts : 0;
	$rp_total  = $rp_n + $rp_n_pl + count( $rp_acts ) + $rp_n_po;
	$rp_pages  = max( (int) ceil( $rp_n / $rp_per ), $rp_n_po ? (int) $wp_query->max_num_pages : 0 );
	$rp_parts  = array_filter( [
		$rp_n ? rp_count( $rp_n, 'place', 'places' ) : '',
		$rp_n_pl ? rp_count( $rp_n_pl, 'city or state', 'cities and states' ) : '',
		$rp_acts ? rp_count( count( $rp_acts ), 'activity', 'activities' ) : '',
		$rp_n_po ? rp_count( $rp_n_po, 'post or photo', 'posts and photos' ) : '',
	] );
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Search</div>
      <?php if ( ! $rp_has_q ) : ?>
        <h1>Search RecPlanet</h1>
        <p class="lead" style="margin-top:14px">Type a park, a city or an activity in the search box above.</p>
      <?php else : ?>
        <h1><?php echo esc_html( rp_count( $rp_total, 'result', 'results' ) ); ?> for “<?php echo esc_html( $rp_q ); ?>”</h1>
        <p class="lead" style="margin-top:14px"><?php echo $rp_total ? esc_html( 'Found: ' . implode( ', ', $rp_parts ) . '.' ) : 'Nothing found. Check the spelling, or try a shorter word: a park name, a city, or an activity such as fishing.'; ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ( $rp_total ) : ?>
  <div class="sortbar"><span class="lbl">Show</span>
    <?php foreach ( [ 20, 50, 100, 200 ] as $rp_size ) : ?><a class="<?php echo $rp_per === $rp_size ? 'on' : ''; ?>" href="<?php echo esc_url( 20 === $rp_size ? remove_query_arg( [ 'per', 'pg' ] ) : add_query_arg( 'per', $rp_size, remove_query_arg( 'pg' ) ) ); ?>"><?php echo (int) $rp_size; ?></a><?php endforeach; ?>
    <span class="lbl">per page</span>
  </div>
  <?php endif; ?>

  <?php if ( $rp_hits ) : ?>
    <h2 class="h26" style="margin-top:20px">Places</h2>
    <p class="note" style="margin:0 0 10px"><?php echo esc_html( rp_range_line( 'Places', $rp_pg, $rp_per, $rp_n ) ); ?>, largest first.</p>
    <div class="plist"><div class="phd"><span>Place</span><span>Activities</span><span style="text-align:right">Acres</span></div><?php foreach ( $rp_hits as $r ) { echo rp_park_row( $r ); } ?></div>
  <?php endif; ?>

  <?php if ( 1 === $rp_pg && $rp_places ) : ?>
    <h2 class="h26" style="margin-top:28px">Cities and states</h2>
    <div class="chips"><?php foreach ( $rp_places as $p ) : ?><a class="chip" href="<?php echo esc_url( get_term_link( $p ) ); ?>"><?php echo esc_html( $p->name ); ?> <span class="n"><?php echo esc_html( number_format( $p->count ) ); ?></span></a><?php endforeach; ?></div>
    <?php if ( $rp_n_pl > count( $rp_places ) ) : ?><p class="note">The <?php echo (int) count( $rp_places ); ?> with the most places, of <?php echo esc_html( number_format( $rp_n_pl ) ); ?>. Add more of the name to narrow it.</p><?php endif; ?>
  <?php endif; ?>

  <?php if ( 1 === $rp_pg && $rp_acts ) : ?>
    <h2 class="h26" style="margin-top:28px">Activities</h2>
    <div class="chips"><?php foreach ( $rp_acts as $p ) : ?><a class="chip" href="<?php echo esc_url( get_term_link( $p ) ); ?>"><?php echo esc_html( $p->name ); ?></a><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if ( $rp_has_q && have_posts() ) : ?>
    <h2 class="h26" style="margin-top:28px">Posts and photos</h2>
    <p class="note" style="margin:0 0 10px"><?php echo esc_html( rp_range_line( 'Posts and photos', $rp_pg, $rp_per, $rp_n_po ) ); ?>.</p>
    <div class="posts">
      <?php while ( have_posts() ) : the_post(); ?>
        <a class="post" href="<?php the_permalink(); ?>"><time><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time><h3><?php the_title(); ?></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 24 ) ); ?></p></a>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <?php echo rp_query_pager( $rp_pg, $rp_pages ); ?>
  <div style="height:40px"></div>
</div>
<?php
	get_footer();
	return;
endif;
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Blog</div>
      <h1><?php echo is_home() ? 'From the trail notebook' : wp_strip_all_tags( get_the_archive_title() ); ?></h1>
    </div>
  </div>
  <?php if ( have_posts() ) : ?>
  <div class="posts" style="margin-bottom:40px">
    <?php while ( have_posts() ) : the_post(); ?>
      <a class="post" href="<?php the_permalink(); ?>"><time><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time><h3><?php the_title(); ?></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 24 ) ); ?></p></a>
    <?php endwhile; ?>
  </div>
  <nav class="pager" aria-label="Pages"><?php previous_posts_link( '← Newer' ); ?><?php next_posts_link( 'Older →' ); ?></nav>
  <?php else : ?>
  <p class="ph" style="margin-bottom:40px">Nothing here yet.</p>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
