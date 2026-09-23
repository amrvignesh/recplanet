<?php
get_header();
$recent = rp_index_rows( 'lat IS NOT NULL', [], 'post_id DESC', 3 );
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Not found</div>
      <h1>That trail doesn't go anywhere</h1>
      <p class="lead" style="margin-top:14px">The page you asked for isn't on file. Try a search, or start from a place.</p>
      <form class="search big" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get"><label for="q404" class="visually-hidden">Search</label><input id="q404" type="search" name="s" placeholder="Park, city or activity"><button class="btn btn-orange" type="submit">Search</button></form>
    </div>
  </div>
  <?php if ( $recent ) : ?><h2 class="h26">Just added</h2><div class="cards" style="margin-bottom:48px"><?php foreach ( $recent as $r ) { echo rp_park_card( $r ); } ?></div><?php endif; ?>
</div>
<?php get_footer(); ?>
