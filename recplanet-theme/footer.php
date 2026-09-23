</main>
<footer>
  <div class="wrap">
    <div>
      <?php echo rp_logo( 'flogo' ); ?>
      <p class="about">Founded by Taylor Marshall in 2008. Every description is written here, never copied. No scraper has ever touched this database.</p>
    </div>
    <div class="cols">
      <div><h4>Explore</h4>
        <?php if ( has_nav_menu( 'footer-explore' ) ) { wp_nav_menu( [ 'theme_location' => 'footer-explore', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] ); } else { ?>
        <a href="<?php echo esc_url( home_url( '/atlas' ) ); ?>">Near me</a><a href="<?php echo esc_url( home_url( '/acre-counter/' ) ); ?>">Acre Counter</a><a href="<?php echo esc_url( home_url( '/#ledger' ) ); ?>">Newest places</a><a href="<?php echo esc_url( home_url( '/states' ) ); ?>">States and provinces</a><a href="<?php echo esc_url( home_url( '/world' ) ); ?>">World Parks</a><a href="<?php echo esc_url( home_url( '/activities' ) ); ?>">Activities</a><a href="<?php echo esc_url( home_url( '/blog' ) ); ?>">Blog</a><a href="<?php echo esc_url( home_url( '/blog-tags/' ) ); ?>">Blog tags</a>
        <?php } ?>
      </div>
      <div><h4>Take part</h4>
        <?php if ( has_nav_menu( 'footer-take-part' ) ) { wp_nav_menu( [ 'theme_location' => 'footer-take-part', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] ); } else { ?>
        <a href="<?php echo esc_url( home_url( '/contest' ) ); ?>">Photo contest</a><a href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a><a href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a><a href="<?php echo esc_url( home_url( '/rules' ) ); ?>">Contest rules</a>
        <?php } ?>
      </div>
      <div><h4>About</h4>
        <?php if ( has_nav_menu( 'footer-about' ) ) { wp_nav_menu( [ 'theme_location' => 'footer-about', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] ); } else { ?>
        <a href="<?php echo esc_url( home_url( '/about-us' ) ); ?>">About us</a><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a><a href="https://www.facebook.com/pages/RecPlanet/127298557845" rel="noopener" target="_blank">Facebook</a>
        <?php } ?>
      </div>
    </div>
  </div>
  <div class="wrap copy">© Taylor Marshall, 2008–<?php echo esc_html( gmdate( 'Y' ) ); ?>. Where the world is your playground.</div>
</footer>
<?php if ( is_front_page() ) { get_template_part( 'template-parts/trail' ); } ?>
<div id="congrats" class="congrats" hidden></div>
<?php wp_footer(); ?>
</body>
</html>
