<?php
/** One photo: the image, the star rating, the place it was taken, more by the photographer. */
get_header();
the_post();
$id      = get_the_ID();
$park    = (int) get_post_meta( $id, 'rp_park_id', true );
$contest = (int) get_post_meta( $id, 'rp_contest_id', true );
$by      = get_post_meta( $id, 'rp_contributor', true ) ?: get_the_author();
$taken   = get_post_meta( $id, 'rp_taken_on', true );
$more    = get_posts( [ 'post_type' => 'rp_photo', 'posts_per_page' => 4, 'post_status' => 'publish', 'exclude' => [ $id ], 'author' => get_post_field( 'post_author', $id ) ?: 0,
	'meta_query' => get_post_meta( $id, 'rp_contributor', true ) ? [ [ 'key' => 'rp_contributor', 'value' => get_post_meta( $id, 'rp_contributor', true ) ] ] : [] ] );
$crumbs  = [ [ 'Contest', home_url( '/contest' ) ] ];
if ( $contest ) { $crumbs[] = [ get_the_title( $contest ), get_permalink( $contest ) ]; }
$crumbs[] = [ get_the_title(), get_permalink() ];
?>
<?php echo rp_crumbs( $crumbs ); ?>
<div class="wrap" style="padding-block:34px">
  <div class="photo-hero">
    <div class="frame"><?php echo has_post_thumbnail() ? get_the_post_thumbnail( $id, 'full' ) : rp_scene_svg( $id ); ?></div>
    <div class="side">
      <div>
        <div class="eyebrow"><?php echo $contest ? 'Contest entry' : 'Photo'; ?><?php echo $park ? ' · taken at a place on file' : ''; ?></div>
        <h1 style="font-size:36px"><?php the_title(); ?></h1>
        <p style="margin-top:10px;color:var(--muted)">by <b><?php echo esc_html( $by ); ?></b><?php if ( $taken ) { echo ' · ' . esc_html( wp_date( 'j F Y', strtotime( $taken ) ) ); } elseif ( get_the_date() ) { echo ' · ' . esc_html( get_the_date( 'j F Y' ) ); } ?><?php if ( $contest ) { echo ' · entered in <a href="' . esc_url( get_permalink( $contest ) ) . '">' . esc_html( get_the_title( $contest ) ) . '</a>'; } ?></p>
        <?php if ( get_the_content() ) : ?><div class="entry" style="margin-top:12px"><?php the_content(); ?></div><?php endif; ?>
      </div>
      <div><?php echo rp_stars( $id ); ?><p class="note" style="margin-top:6px">One rating per photo. No account needed; members can change theirs until voting closes.</p></div>
      <?php if ( $park ) : ?>
      <div class="box"><h4>Taken at</h4><a class="row" href="<?php echo esc_url( get_permalink( $park ) ); ?>"><span><?php echo esc_html( get_the_title( $park ) ); ?></span><b class="num"><?php $a = get_post_meta( $park, 'rp_acreage', true ); echo '' === $a ? '' : esc_html( RP\format_acres( (float) $a ) ); ?></b></a><a class="more" href="<?php echo esc_url( get_permalink( $park ) ); ?>">See the place →</a></div>
      <?php endif; ?>
      <?php if ( $more ) : ?>
      <div class="box"><h4>More from this photographer</h4><?php foreach ( $more as $m ) : ?><a class="row" href="<?php echo esc_url( get_permalink( $m ) ); ?>"><span><?php echo esc_html( get_the_title( $m ) ); ?></span><b><?php echo esc_html( get_the_date( 'Y', $m ) ); ?></b></a><?php endforeach; ?></div>
      <?php endif; ?>
      <?php $tags = get_the_tags(); if ( $tags ) : ?><div class="chips"><?php foreach ( $tags as $t ) : ?><a class="chip" href="<?php echo esc_url( get_tag_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
  <?php if ( comments_open() || get_comments_number() ) { echo '<div style="max-width:72ch;margin-top:36px">'; comments_template(); echo '</div>'; } ?>
</div>
<?php get_footer(); ?>
