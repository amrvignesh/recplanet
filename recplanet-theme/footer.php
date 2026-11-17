</main>
<?php $rp_fw = RP\Counter::get( 'world', 'world' ); ?>
<footer class="foot" id="foot">
  <div class="wrap top">
    <div class="cols">
      <div class="brand">
        <?php echo rp_logo( 'flogo' ); ?>
        <p class="tag">Where the World is your Playground.</p>
        <p class="count"><b class="num"><?php echo esc_html( RP\format_acres( $rp_fw['acres'] ) ); ?></b><span>acres on file across <?php echo esc_html( number_format( $rp_fw['count'] ) ); ?> places, each one checked by a person.</span></p>
        <p class="founder">Founded by Taylor Marshall in 2008. Every description is written here, never copied.</p>
      </div>
      <div class="col"><h4>Explore</h4>
        <?php if ( has_nav_menu( 'footer-explore' ) ) { wp_nav_menu( [ 'theme_location' => 'footer-explore', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] ); } else { ?>
        <a href="<?php echo esc_url( home_url( '/atlas/' ) ); ?>">Near Me</a><a href="<?php echo esc_url( home_url( '/states/' ) ); ?>">Parks by State</a><a href="<?php echo esc_url( home_url( '/world/' ) ); ?>">World Parks</a><a href="<?php echo esc_url( home_url( '/activities/' ) ); ?>">Activities</a><a href="<?php echo esc_url( home_url( '/acre-counter/' ) ); ?>">Acre Counter</a><a href="<?php echo esc_url( home_url( '/#ledger' ) ); ?>">Newest places</a>
        <?php } ?>
      </div>
      <div class="col"><h4>Take part</h4>
        <?php if ( has_nav_menu( 'footer-take-part' ) ) { wp_nav_menu( [ 'theme_location' => 'footer-take-part', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] ); } else { ?>
        <a href="<?php echo esc_url( home_url( '/contest/' ) ); ?>">Photo contest</a><?php if ( is_user_logged_in() ) : ?><a href="<?php echo esc_url( home_url( '/contest/#enter' ) ); ?>">Add a photo</a><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a><?php else : ?><a href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a><a href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a><?php endif; ?><a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>">Contest rules</a>
        <?php } ?>
      </div>
      <div class="col"><h4>About</h4>
        <?php if ( has_nav_menu( 'footer-about' ) ) { wp_nav_menu( [ 'theme_location' => 'footer-about', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] ); } else { ?>
        <a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">About us</a><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a><a href="<?php echo esc_url( home_url( '/blog-tags/' ) ); ?>">Blog tags</a><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us</a><a href="<?php echo esc_url( RP\Settings::facebook_url() ); ?>" rel="noopener" target="_blank">Facebook</a>
        <?php } ?>
      </div>
      <div class="find"><h4>Find a park</h4>
        <label for="rp-foot-q">Somewhere in mind? Type a city, a state or a park.</label>
        <form role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
          <input id="rp-foot-q" type="search" name="s" placeholder="Playgrounds in Plano">
          <button type="submit" aria-label="Search parks"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h14M11 4l6 6-6 6"/></svg></button>
        </form>
        <a class="rand" href="<?php echo esc_url( home_url( '/random/' ) ); ?>">Or surprise me with a random park →</a>
      </div>
    </div>
    <div class="legal">
      <div class="say"><span class="wide">Coast to coast in thirteen landmarks. Yours is in here somewhere.</span><span class="narrow">Swipe coast to coast →</span></div>
      <div class="links"><span>© Taylor Marshall, 2008–<?php echo esc_html( gmdate( 'Y' ) ); ?></span><a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>">Contest rules</a><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a><a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>">Sitemap</a></div>
    </div>
  </div>
  <?php get_template_part( 'template-parts/footer-panorama' ); ?>
</footer>
<?php if ( is_front_page() ) { get_template_part( 'template-parts/trail' ); } ?>
<div id="congrats" class="congrats" hidden></div>
<?php if ( is_singular( 'rp_photo' ) ) : ?>
<dialog id="voteDlg" class="vote-dlg" data-msg="<?php echo esc_attr( RP\Settings::vote_message() ); ?>" aria-labelledby="voteDlgTitle">
  <div class="vd-main">
    <div class="eyebrow" id="voteDlgTitle">Thank you for voting</div>
    <div class="vd-text"></div>
    <div class="vd-actions"><a class="btn btn-ghost" href="<?php echo esc_url( RP\Settings::facebook_url() ); ?>" target="_blank" rel="noopener">Follow us on Facebook</a><button type="button" class="btn btn-orange vd-close">Keep voting</button></div>
  </div>
  <?php $rp_ad = rp_ad_slot( 'vote_dialog' ); if ( $rp_ad ) : ?><div class="vd-ad"><?php echo $rp_ad; ?></div><?php endif; ?>
</dialog>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
