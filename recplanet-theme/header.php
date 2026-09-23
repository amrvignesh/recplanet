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
<?php
$rp_world  = RP\Counter::get( 'world', 'world' );
$rp_states = get_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => true, 'meta_key' => 'rp_level', 'meta_value' => 'state', 'number' => 12, 'orderby' => 'count', 'order' => 'DESC' ] );
$rp_acts   = get_terms( [ 'taxonomy' => 'rp_activity', 'hide_empty' => true, 'number' => 12, 'orderby' => 'count', 'order' => 'DESC' ] );
$rp_short  = $rp_world['acres'] >= 1e9 ? number_format( $rp_world['acres'] / 1e9, 2 ) . 'B' : number_format( $rp_world['acres'] / 1e6, 1 ) . 'M';
$rp_here   = fn( string $slug ) => is_page( $slug ) ? ' aria-current="page" class="on"' : '';
$rp_icons  = [
	'pin'     => '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a6 6 0 0 1 6 6c0 4.5-6 10-6 10S4 12.5 4 8a6 6 0 0 1 6-6z"/><circle cx="10" cy="8" r="2.2"/></svg>',
	'map'     => '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M2 5l6-2 6 2 4-1v11l-4 1-6-2-6 2z"/><path d="M8 3v11M14 5v11"/></svg>',
	'globe'   => '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M2 10h16M10 2c3 3 3 13 0 16M10 2c-3 3-3 13 0 16"/></svg>',
	'hike'    => '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="12" cy="3.5" r="1.8"/><path d="M9 6l-3 8M9 6l5 3v6M9 6l-4 3M10 12l-3 6"/></svg>',
	'counter' => '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 15l4-5 4 3 6-8"/><path d="M13 5h4v4"/></svg>',
	'camera'  => '<svg viewBox="0 0 20 20" aria-hidden="true"><rect x="2" y="6" width="16" height="11" rx="2"/><path d="M7 6l1.5-2.5h3L13 6"/><circle cx="10" cy="11.5" r="3"/></svg>',
	'blog'    => '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 3h9l3 3v11H4z"/><path d="M7 9h6M7 12h6M7 15h4"/></svg>',
];
?>
<div class="topbar">
  <div class="wrap">
    <a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="RecPlanet home"><?php echo rp_logo(); ?></a>
    <form class="search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="#5A6862" stroke-width="2" aria-hidden="true"><circle cx="8.5" cy="8.5" r="6"/><path d="M13 13l4.5 4.5"/></svg>
      <label for="q" class="visually-hidden">Search parks, cities, activities</label>
      <input id="q" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Search parks, cities, activities" data-hints="Try: playgrounds in Plano|Try: fishing near Austin|Try: Fritz Park|Try: dog parks in Denver|Try: Yellowstone">
    </form>
    <div class="account">
      <?php if ( ! is_front_page() ) : ?><a class="acre-chip" href="<?php echo esc_url( home_url( '/acre-counter/' ) ); ?>" title="<?php echo esc_attr( RP\format_acres( $rp_world['acres'] ) . ' acres on file' ); ?>"><b class="num"><?php echo esc_html( $rp_short ); ?></b><span>acres</span></a><?php endif; ?>
      <?php if ( is_user_logged_in() ) : ?>
        <a class="join" href="<?php echo esc_url( home_url( '/contest/' ) ); ?>">Add a photo</a>
        <a class="signin" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a>
      <?php else : ?>
        <a class="join" href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a>
        <a class="signin" href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a>
      <?php endif; ?>
    </div>
    <button class="menu-btn" type="button" id="menuBtn" aria-expanded="false" aria-controls="menu" aria-label="Menu"><svg width="22" height="22" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h16M3 11h16M3 16h16"/></svg></button>
  </div>
</div>
<div class="bar" id="bar">
  <div class="wrap">
    <nav id="menu" aria-label="Primary">
      <form class="menu-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get"><label for="qm" class="visually-hidden">Search</label><input id="qm" type="search" name="s" placeholder="Search parks, cities, activities"><button type="submit" aria-label="Search"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8.5" cy="8.5" r="6"/><path d="M13 13l4.5 4.5"/></svg></button></form>
      <a href="<?php echo esc_url( home_url( '/atlas/' ) ); ?>"<?php echo $rp_here( 'atlas' ); ?>><?php echo $rp_icons['pin']; ?><span class="lbl">Near Me</span></a>
      <div class="has-sub">
        <a href="<?php echo esc_url( home_url( '/states/' ) ); ?>"<?php echo $rp_here( 'states' ); ?>><?php echo $rp_icons['map']; ?><span class="lbl">Parks by State</span></a>
        <div class="sub">
          <div class="sub-grid">
            <?php foreach ( $rp_states as $st ) : $s = rp_place_stats( $st ); ?><a href="<?php echo esc_url( get_term_link( $st ) ); ?>"><b><?php echo esc_html( $st->name ); ?></b><span><?php echo esc_html( number_format( $s['count'] ) ); ?> places</span></a><?php endforeach; ?>
          </div>
          <a class="sub-all" href="<?php echo esc_url( home_url( '/states/' ) ); ?>">Every state, A to Z →</a>
        </div>
      </div>
      <a href="<?php echo esc_url( home_url( '/world/' ) ); ?>"<?php echo $rp_here( 'world' ); ?>><?php echo $rp_icons['globe']; ?><span class="lbl">World Parks</span></a>
      <div class="has-sub">
        <a href="<?php echo esc_url( home_url( '/activities/' ) ); ?>"<?php echo $rp_here( 'activities' ); ?>><?php echo $rp_icons['hike']; ?><span class="lbl">Activities</span></a>
        <div class="sub">
          <div class="sub-chips">
            <?php foreach ( $rp_acts as $a ) : ?><a class="chip" href="<?php echo esc_url( get_term_link( $a ) ); ?>"><?php echo esc_html( $a->name ); ?> <span class="n"><?php echo esc_html( number_format( $a->count ) ); ?></span></a><?php endforeach; ?>
          </div>
          <a class="sub-all" href="<?php echo esc_url( home_url( '/activities/' ) ); ?>">All 44 activities →</a>
        </div>
      </div>
      <a href="<?php echo esc_url( home_url( '/acre-counter/' ) ); ?>"<?php echo $rp_here( 'acre-counter' ); ?>><?php echo $rp_icons['counter']; ?><span class="lbl">Acre Counter</span></a>
      <a href="<?php echo esc_url( home_url( '/contest/' ) ); ?>"<?php echo is_post_type_archive( 'rp_contest' ) || is_singular( [ 'rp_contest', 'rp_photo' ] ) ? ' class="on"' : ''; ?>><?php echo $rp_icons['camera']; ?><span class="lbl">Contest</span></a>
      <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"<?php echo is_home() || is_singular( 'post' ) ? ' class="on"' : ''; ?>><?php echo $rp_icons['blog']; ?><span class="lbl">Blog</span></a>
      <div class="menu-mobile-extra">
        <?php if ( ! is_front_page() ) : ?><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><?php endif; ?>
        <a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">About</a>
        <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
        <?php if ( is_user_logged_in() ) : ?><a href="<?php echo esc_url( home_url( '/contest/' ) ); ?>">Add a photo</a><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a><?php else : ?><a href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a><a href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a><?php endif; ?>
      </div>
    </nav>
  </div>
</div>
<nav class="tabbar" aria-label="Quick links">
  <a href="<?php echo esc_url( home_url( '/atlas/' ) ); ?>"<?php echo $rp_here( 'atlas' ); ?>><?php echo $rp_icons['pin']; ?><span>Near me</span></a>
  <a href="<?php echo esc_url( home_url( '/states/' ) ); ?>"<?php echo $rp_here( 'states' ); ?>><?php echo $rp_icons['map']; ?><span>States</span></a>
  <a href="<?php echo esc_url( home_url( '/activities/' ) ); ?>"<?php echo $rp_here( 'activities' ); ?>><?php echo $rp_icons['hike']; ?><span>Activities</span></a>
  <a href="<?php echo esc_url( home_url( '/contest/' ) ); ?>"><?php echo $rp_icons['camera']; ?><span>Contest</span></a>
  <a href="<?php echo esc_url( home_url( '/world/' ) ); ?>"<?php echo $rp_here( 'world' ); ?>><?php echo $rp_icons['globe']; ?><span>World</span></a>
</nav>
<main id="main">
