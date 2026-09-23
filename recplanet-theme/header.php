<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( is_front_page() ? 'on-home trip-hiker' : '' ); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main">Skip to content</a>
<div class="bar">
  <div class="wrap">
    <a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="RecPlanet home"><?php echo rp_logo(); ?></a>
    <form class="search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="#5A6862" stroke-width="2" aria-hidden="true"><circle cx="8.5" cy="8.5" r="6"/><path d="M13 13l4.5 4.5"/></svg>
      <label for="q" class="visually-hidden">Search parks, cities, activities</label>
      <input id="q" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Search parks, cities, activities">
    </form>
    <nav aria-label="Primary">
      <?php
      if ( has_nav_menu( 'primary' ) ) {
        wp_nav_menu( [ 'theme_location' => 'primary', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] );
      } else {
        echo '<a href="' . esc_url( home_url( '/atlas' ) ) . '">Near me</a><a href="' . esc_url( home_url( '/states' ) ) . '">Places</a><a href="' . esc_url( home_url( '/activities' ) ) . '">Activities</a><a href="' . esc_url( home_url( '/contest' ) ) . '">Contest</a><a href="' . esc_url( home_url( '/blog' ) ) . '">Blog</a>';
      }
      ?>
    </nav>
    <?php if ( is_user_logged_in() ) : ?>
      <a class="join" href="<?php echo esc_url( home_url( '/my-photos' ) ); ?>">My photos</a>
    <?php else : ?>
      <a class="join" href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a>
    <?php endif; ?>
  </div>
</div>
<main id="main">
