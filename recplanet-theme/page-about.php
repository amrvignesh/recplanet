<?php
/**
 * Template Name: About
 * The About page: the founder's text, then the story of the counter in numbers.
 */
get_header();
the_post();
$world = RP\Counter::get( 'world', 'world' );
?>
<div class="wrap">
  <article class="article">
    <h1><?php the_title(); ?></h1>
    <div class="entry"><?php the_content(); ?></div>
  </article>
  <section style="padding-top:0">
    <div class="section-head"><div><div class="eyebrow">Since 2008</div><h2>How the counter got to <?php echo esc_html( RP\format_acres( $world['acres'], 0 ) ); ?> acres</h2></div><p>Every record was entered by hand and checked for what is really there. This is the pace of that work, and the most recent additions.</p></div>
    <?php get_template_part( 'template-parts/growth' ); ?>
  </section>
</div>
<?php get_footer(); ?>
