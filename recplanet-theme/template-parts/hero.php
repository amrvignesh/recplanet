<?php
/** The hero: hills from the logo, the counter, time of day and season set by main.js. */
$world = $args['world'];
$us    = $args['us'];
$aq    = $args['aq'];
$rest  = $args['rest'];
$head  = 'us' === $args['headline'] ? $us : $world;
$whole = floor( $head['acres'] );
$dec   = (int) round( ( $head['acres'] - $whole ) * 100 );
?>
<header class="hero" id="hero" data-acres="<?php echo esc_attr( $head['acres'] ); ?>">
  <div class="skybg"></div>
  <div class="stars" aria-hidden="true"></div>
  <div class="sun" data-depth="0.35"></div>
  <div class="cloud c3" aria-hidden="true"></div>
  <div class="cloud c1" aria-hidden="true"></div>
  <div class="cloud c2" aria-hidden="true"></div>
  <div class="layer l1" data-depth="0.30" style="opacity:.6"><svg viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true"><path class="hill" d="M0 230 C 180 150, 320 150, 480 200 S 760 260, 960 190 S 1260 120, 1440 180 V320 H0 Z"/></svg></div>
  <div class="layer l2" data-depth="0.20"><svg viewBox="0 0 1440 300" preserveAspectRatio="none" aria-hidden="true"><path class="hill" d="M0 210 C 160 120, 360 120, 540 190 S 880 250, 1080 160 S 1320 110, 1440 170 V300 H0 Z"/></svg></div>
  <div class="layer l3" data-depth="0.10"><svg viewBox="0 0 1440 260" preserveAspectRatio="none" aria-hidden="true"><path class="hill" d="M0 190 C 220 90, 420 110, 640 170 S 1000 220, 1200 140 S 1380 110, 1440 150 V260 H0 Z"/></svg></div>
  <div class="layer l4" data-depth="0.04"><svg viewBox="0 0 1440 220" preserveAspectRatio="none" aria-hidden="true">
    <path class="hill" d="M0 150 C 240 60, 500 90, 720 140 S 1120 200, 1440 110 V220 H0 Z"/>
    <g>
      <path class="fig" d="M300 118 l-6 -34 l6 -14 l6 14 l-6 34 z M300 70 l-5 -12 l5 -10 l5 10 z"/>
      <path class="fig" d="M330 126 l-7 -40 l7 -16 l7 16 l-7 40 z M330 70 l-6 -14 l6 -12 l6 12 z"/>
      <path class="fig" d="M980 120 l-6 -34 l6 -14 l6 14 l-6 34 z M980 72 l-5 -12 l5 -10 l5 10 z"/>
      <path class="fig" d="M1010 128 l-7 -40 l7 -16 l7 16 l-7 40 z M1010 72 l-6 -14 l6 -12 l6 12 z"/>
      <circle class="fig" cx="720" cy="112" r="4"/><path class="figl" d="M720 116 l-3 14 l-4 10 M720 116 l3 14 l4 10 M720 118 l-8 4 M720 118 l8 -6 l3 -8" stroke-width="2.4" fill="none" stroke-linecap="round"/>
      <circle class="fig" cx="1160" cy="122" r="4"/><path class="figl" d="M1160 126 l-3 14 l-4 10 M1160 126 l3 14 l4 10 M1160 128 l-8 4 M1160 128 l8 -6" stroke-width="2.4" fill="none" stroke-linecap="round"/>
    </g>
  </svg></div>

  <div class="wrap hero-inner">
    <div class="brand"><?php echo rp_logo(); ?></div>
    <h1 class="tag script"><?php echo esc_html( get_bloginfo( 'description' ) ?: 'Where the world is your playground.' ); ?></h1>
    <div class="counter num" aria-live="polite"><span class="big" id="acresInt"><?php echo esc_html( number_format( $whole ) ); ?></span><span class="dec" id="acresDec">.<?php echo esc_html( str_pad( (string) $dec, 2, '0', STR_PAD_LEFT ) ); ?></span></div>
    <p class="counter-label">acres of recreation and conservation land, held in common, counted one park at a time. The only big number in the news that goes up.</p>
    <div class="counter-sub">
      <?php if ( 'us' === $args['headline'] ) : ?>
        <span>World <b><?php echo esc_html( RP\format_acres( $world['acres'] ) ); ?></b></span>
      <?php else : ?>
        <span>United States <b><?php echo esc_html( RP\format_acres( $us['acres'] ) ); ?></b></span>
      <?php endif; ?>
      <span>Rest of world <b><?php echo esc_html( RP\format_acres( $rest['acres'] ) ); ?></b></span>
      <?php if ( $aq['acres'] > 0 ) : ?><span>Antarctica <b><?php echo esc_html( RP\format_acres( $aq['acres'] ) ); ?></b></span><?php endif; ?>
    </div>
    <div class="hero-cta">
      <a class="btn btn-orange" href="<?php echo esc_url( home_url( '/atlas' ) ); ?>">Find fun near me</a>
      <a class="btn btn-white" href="<?php echo esc_url( home_url( '/random/' ) ); ?>">Surprise me</a>
    </div>
  </div>
</header>
