<?php
/** The contest hub: the open contest, the entry form, entries by rating, past contests. */
get_header();
$open = get_posts( [ 'post_type' => 'rp_contest', 'posts_per_page' => 1, 'post_status' => 'publish', 'meta_query' => [ [ 'key' => 'rp_status', 'value' => [ 'open', 'voting' ], 'compare' => 'IN' ] ] ] );
$open = $open ? $open[0] : null;
$past = get_posts( [ 'post_type' => 'rp_contest', 'posts_per_page' => 12, 'post_status' => 'publish', 'exclude' => $open ? [ $open->ID ] : [] ] );
$sort = sanitize_key( $_GET['sort'] ?? 'top' );
$entries = get_posts( [ 'post_type' => 'rp_photo', 'posts_per_page' => 24, 'post_status' => 'publish', 'paged' => max( 1, (int) get_query_var( 'paged' ) ),
	'meta_key' => 'newest' === $sort ? '' : 'rp_score', 'orderby' => 'newest' === $sort ? 'date' : 'meta_value_num', 'order' => 'DESC',
	'meta_query' => $open ? [ [ 'key' => 'rp_contest_id', 'value' => $open->ID ] ] : [] ] );
$park_pre = ! empty( $_GET['park'] ) ? get_post( (int) $_GET['park'] ) : null;
?>
<div class="wrap" style="padding-block:34px">
  <div class="contest-hero">
    <div>
      <div class="eyebrow" style="color:var(--lime)">Photo contest</div>
      <h1 style="color:#fff"><?php echo esc_html( $park_pre ? 'Add a photo of ' . get_the_title( $park_pre ) : ( $open ? get_the_title( $open ) : 'Photograph the places you love' ) ); ?></h1>
      <p style="margin-top:12px;font-size:17px"><?php echo $open ? wp_kses_post( wpautop( $open->post_content ) ) : 'Any recreational photo can enter: a sunset over a lake, a trail, a bird, a frog on a log. Taken in a park or recreation area, rated by anyone from one to five stars. Prizes go to photographers and to voters alike.'; ?></p>
      <?php if ( $open ) : ?>
      <div class="dates">
        <div><small>Entries open</small><b><?php echo esc_html( get_post_meta( $open->ID, 'rp_opens_at', true ) ?: '—' ); ?></b></div>
        <div><small>Entries close</small><b><?php echo esc_html( get_post_meta( $open->ID, 'rp_closes_at', true ) ?: '—' ); ?></b></div>
        <div><small>Voting closes</small><b><?php echo esc_html( get_post_meta( $open->ID, 'rp_voting_closes_at', true ) ?: '—' ); ?></b></div>
        <?php if ( get_post_meta( $open->ID, 'rp_prizes', true ) ) : ?><div><small>Prizes</small><b><?php echo esc_html( get_post_meta( $open->ID, 'rp_prizes', true ) ); ?></b></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap"><a class="btn btn-white" href="<?php echo esc_url( home_url( '/rules' ) ); ?>">Contest rules</a></div>
    </div>
    <?php get_template_part( 'template-parts/upload-form', null, [ 'contest' => $open, 'park' => $park_pre ] ); ?>
  </div>

  <section>
    <div class="section-head"><div><div class="eyebrow"><?php echo $open ? 'Entries' : 'Photos'; ?></div><h2><?php echo $open ? 'Rate the entries' : 'From the members'; ?></h2></div>
      <div class="sortbar" style="margin:0"><a class="<?php echo 'top' === $sort ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sort', 'top' ) ); ?>">Top rated</a><a class="<?php echo 'newest' === $sort ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sort', 'newest' ) ); ?>">Newest</a></div></div>
    <div class="gallery">
      <?php foreach ( $entries as $p ) : $sc = (float) get_post_meta( $p->ID, 'rp_score', true ); $n = (int) get_post_meta( $p->ID, 'rp_votes', true ); ?>
        <a class="tile" href="<?php echo esc_url( get_permalink( $p ) ); ?>" style="--r:0"><?php echo has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'rp-tile', [ 'loading' => 'lazy' ] ) : rp_scene_svg( $p->ID ); ?><span class="votes">★ <?php echo esc_html( number_format( $sc, 1 ) ); ?> · <?php echo esc_html( number_format( $n ) ); ?></span><div class="cap"><?php echo esc_html( get_the_title( $p ) ); ?><small><?php echo esc_html( get_post_meta( $p->ID, 'rp_contributor', true ) ?: get_the_author_meta( 'display_name', $p->post_author ) ); ?></small></div></a>
      <?php endforeach; ?>
      <?php if ( ! $entries ) : ?><p class="ph">No entries yet. Yours could be the first.</p><?php endif; ?>
    </div>
  </section>

  <?php if ( $past ) : ?>
  <section style="padding-top:0">
    <div class="section-head"><div><div class="eyebrow">Archive</div><h2>Past contests</h2></div><p>Every entry and every rating from earlier contests, kept as they were, with every photographer credited.</p></div>
    <div class="posts">
      <?php foreach ( $past as $c ) : $n = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key='rp_contest_id' AND meta_value=%d", $c->ID ) ); ?>
        <a class="post" href="<?php echo esc_url( get_permalink( $c ) ); ?>"><time><?php echo esc_html( get_post_meta( $c->ID, 'rp_opens_at', true ) ); ?> to <?php echo esc_html( get_post_meta( $c->ID, 'rp_closes_at', true ) ); ?></time><h3><?php echo esc_html( get_the_title( $c ) ); ?></h3><p><?php echo esc_html( number_format( $n ) ); ?> entries</p></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
<?php get_footer(); ?>
