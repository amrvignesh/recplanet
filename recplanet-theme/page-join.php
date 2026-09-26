<?php
/**
 * Template Name: Join
 * Front-end registration: name, email, password and the same anti-spam guard as the contact form.
 */
if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/contest/' ) );
	exit;
}
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );   // the form carries a signed challenge and a start time; never serve a cached copy
}
nocache_headers();
get_header();
$site = get_option( 'rp_turnstile_site', '' );
$chal = $site ? null : RP\Messages::challenge();
$to   = wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ?? '' ), '' );
?>
<div class="wrap">
  <div class="phead">
    <div>
      <div class="eyebrow">Join free</div>
      <h1><?php the_title(); ?></h1>
      <div class="lead entry" style="margin-top:14px"><?php the_post(); the_content(); ?></div>
      <div class="box" style="margin-top:22px"><h4>What members can do</h4>
        <a class="row" href="<?php echo esc_url( home_url( '/contest/' ) ); ?>"><span>Enter the photo contest</span><b>→</b></a>
        <a class="row" href="<?php echo esc_url( home_url( '/atlas/' ) ); ?>"><span>Add a photo to any park&rsquo;s page</span><b>→</b></a>
        <a class="row" href="<?php echo esc_url( home_url( '/rules/' ) ); ?>"><span>Read the rules</span><b>→</b></a>
        <p class="note" style="margin:6px 0 0">Rating photos needs no account. Already a member? <a href="<?php echo esc_url( home_url( '/sign-in/' ) ); ?>">Sign in</a>.</p>
      </div>
    </div>
    <form class="contact-form auth-form" id="joinForm" novalidate>
      <b class="ttl">Create your account</b>
      <label for="j_name">Your name<input id="j_name" name="name" type="text" required maxlength="60" autocomplete="name" placeholder="Shown next to your photos"></label>
      <label for="j_email">Email<input id="j_email" name="email" type="email" required maxlength="120" autocomplete="email"></label>
      <label for="j_pass">Password<input id="j_pass" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least eight characters"></label>
      <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $to ); ?>">
      <?php if ( $site ) : ?>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site ); ?>" data-theme="light"></div>
      <?php else : ?>
        <label for="j_answer" class="captcha"><span><?php echo esc_html( $chal['question'] ); ?></span><input id="j_answer" name="answer" type="text" inputmode="numeric" required autocomplete="off" style="max-width:90px"><input type="hidden" name="token" value="<?php echo esc_attr( $chal['token'] ); ?>"><small>A quick sum keeps the robots out.</small></label>
      <?php endif; ?>
      <button class="btn btn-orange" type="submit">Join free</button>
      <div class="form-msg" id="joinMsg" hidden></div>
      <small class="note" style="margin:0">Free, always. Your email is used only to reach you about your photos.</small>
    </form>
  </div>
</div>
<?php if ( $site ) : ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<?php get_footer(); ?>
