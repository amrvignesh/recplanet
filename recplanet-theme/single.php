<?php
/** A blog post, or any single post type without its own template. */
get_header();
the_post();
?>
<div class="wrap">
  <article class="article">
    <div class="eyebrow"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?> · <?php echo esc_html( get_post_meta( get_the_ID(), 'rp_contributor', true ) ?: get_the_author() ); ?></div>
    <h1><?php the_title(); ?></h1>
    <?php if ( has_post_thumbnail() ) : ?><div class="figure"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
    <div class="entry"><?php the_content(); ?></div>
    <?php $tags = get_the_tags(); if ( $tags ) : ?><div class="chips" style="margin-top:24px"><?php foreach ( $tags as $t ) : ?><a class="chip" href="<?php echo esc_url( get_tag_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
  </article>
</div>
<?php get_footer(); ?>
