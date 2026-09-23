<?php
get_header();
the_post();
?>
<div class="wrap">
  <article class="article">
    <h1><?php the_title(); ?></h1>
    <div class="entry"><?php the_content(); ?></div>
  </article>
</div>
<?php get_footer(); ?>
