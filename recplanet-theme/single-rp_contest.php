<?php
/** One contest: its entries by rating, its winners, its dates. */
get_header();
the_post();
$id      = get_the_ID();
$status  = get_post_meta( $id, 'rp_status', true );
$winners = json_decode( (string) get_post_meta( $id, 'rp_winners', true ), true ) ?: [];
$sort    = sanitize_key( $_GET['sort'] ?? 'top' );
$paged   = max( 1, (int) get_query_var( 'paged' ) );
$q = new WP_Query( [ 'post_type' => 'rp_photo', 'posts_per_page' => 40, 'paged' => $paged, 'post_status' => 'publish',
	'meta_key' => 'newest' === $sort ? '' : 'rp_score', 'orderby' => 'newest' === $sort ? 'date' : 'meta_value_num', 'order' => 'DESC',
	'meta_query' => [ [ 'key' => 'rp_contest_id', 'value' => $id ] ] ] );
$n = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->postmeta} pm JOIN {$GLOBALS['wpdb']->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='rp_contest_id' AND pm.meta_value=%d AND p.post_status='publish'", $id ) );
$votes = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COALESCE(SUM(CAST(v.meta_value AS UNSIGNED)),0) FROM {$GLOBALS['wpdb']->postmeta} pm JOIN {$GLOBALS['wpdb']->postmeta} v ON v.post_id=pm.post_id AND v.meta_key='rp_votes' WHERE pm.meta_key='rp_contest_id' AND pm.meta_value=%d", $id ) );
?>
<?php echo rp_crumbs( [ [ 'Contest', home_url( '/contest' ) ], [ get_the_title(), get_permalink() ] ], number_format( $n ) . ' entries · ' . number_format( $votes ) . ' ratings' ); ?>
<div class="wrap" style="padding-block:34px">
  <div class="phead">
    <div>
      <div class="eyebrow"><?php echo esc_html( 'closed' === $status ? 'Closed contest' : ( 'voting' === $status ? 'Voting open' : ( 'open' === $status ? 'Entries open' : 'Contest' ) ) ); ?></div>
      <h1><?php the_title(); ?></h1>
      <div class="lead entry" style="margin-top:14px"><?php the_content(); ?></div>
      <?php if ( 'open' === $status || 'voting' === $status ) : ?><a class="btn btn-orange" href="<?php echo esc_url( home_url( '/contest#enter' ) ); ?>" style="margin-top:16px">Enter a photo</a><?php endif; ?>
    </div>
    <div class="facts">
      <div><span class="k">Entries</span><span class="v num"><?php echo esc_html( number_format( $n ) ); ?></span></div>
      <div><span class="k">Ratings</span><span class="v num"><?php echo esc_html( number_format( $votes ) ); ?></span></div>
      <div><span class="k">Opened</span><span class="v"><?php echo esc_html( get_post_meta( $id, 'rp_opens_at', true ) ?: '—' ); ?></span></div>
      <div><span class="k">Closed</span><span class="v"><?php echo esc_html( get_post_meta( $id, 'rp_closes_at', true ) ?: '—' ); ?></span></div>
      <?php if ( get_post_meta( $id, 'rp_prizes', true ) ) : ?><div class="wide"><span class="k">Prizes</span><span class="v"><?php echo esc_html( get_post_meta( $id, 'rp_prizes', true ) ); ?></span></div><?php endif; ?>
    </div>
  </div>
  <?php if ( $winners ) : ?>
  <section style="padding-top:10px">
    <div class="section-head"><div><div class="eyebrow">Winners</div><h2>The judges' picks</h2></div></div>
    <div class="gallery">
      <?php foreach ( $winners as $i => $wid ) : if ( ! get_post( (int) $wid ) ) continue; ?>
        <a class="tile" href="<?php echo esc_url( get_permalink( (int) $wid ) ); ?>" style="--r:0"><?php echo has_post_thumbnail( (int) $wid ) ? get_the_post_thumbnail( (int) $wid, 'rp-tile' ) : rp_scene_svg( (int) $wid ); ?><span class="votes">#<?php echo (int) $i + 1; ?></span><div class="cap"><?php echo esc_html( get_the_title( (int) $wid ) ); ?><small><?php echo esc_html( get_post_meta( (int) $wid, 'rp_contributor', true ) ?: get_the_author_meta( 'display_name', get_post_field( 'post_author', (int) $wid ) ) ); ?></small></div></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <section style="padding-top:10px">
    <div class="section-head"><div><div class="eyebrow">Entries</div><h2>Every entry, <?php echo 'newest' === $sort ? 'newest first' : 'highest rated first'; ?></h2></div>
      <div class="sortbar" style="margin:0"><a class="<?php echo 'top' === $sort ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sort', 'top' ) ); ?>">Top rated</a><a class="<?php echo 'newest' === $sort ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sort', 'newest' ) ); ?>">Newest</a></div></div>
    <div class="gallery">
      <?php while ( $q->have_posts() ) : $q->the_post(); $sc = (float) get_post_meta( get_the_ID(), 'rp_score', true ); $nv = (int) get_post_meta( get_the_ID(), 'rp_votes', true ); ?>
        <a class="tile" href="<?php the_permalink(); ?>" style="--r:0"><?php echo has_post_thumbnail() ? get_the_post_thumbnail( get_the_ID(), 'rp-tile', [ 'loading' => 'lazy' ] ) : rp_scene_svg( get_the_ID() ); ?><span class="votes">★ <?php echo esc_html( number_format( $sc, 1 ) ); ?> · <?php echo esc_html( number_format( $nv ) ); ?></span><div class="cap"><?php the_title(); ?><small><?php echo esc_html( get_post_meta( get_the_ID(), 'rp_contributor', true ) ?: get_the_author() ); ?></small></div></a>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php if ( $q->max_num_pages > 1 ) : ?><nav class="pager" aria-label="Pages"><?php if ( $paged > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'sort' => $sort, 'paged' => $paged - 1 ] ) ); ?>">← Previous</a><?php endif; ?><span>Page <?php echo (int) $paged; ?> of <?php echo (int) $q->max_num_pages; ?></span><?php if ( $paged < $q->max_num_pages ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'sort' => $sort, 'paged' => $paged + 1 ] ) ); ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
  </section>
</div>
<?php get_footer(); ?>
