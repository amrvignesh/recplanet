<?php
/**
 * Template Name: Sign in
 * Front-end sign-in. Lost password goes to core's screen, which emails a reset link.
 */
if ( is_user_logged_in() ) {
	wp_safe_redirect( wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ?? '' ), home_url( '/contest/' ) ) );
	exit;
}
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );   // the form carries a signed challenge and a start time; never serve a cached copy
}
nocache_headers();
get_header();
$to = wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ?? '' ), '' );
?>
<div class="wrap">
  <div class="phead">
    <div>
      <div class="eyebrow">Members</div>
      <h1><?php the_title(); ?></h1>
      <div class="lead entry" style="margin-top:14px"><?php the_post(); the_content(); ?></div>
      <div class="box" style="margin-top:22px"><h4>Not a member yet?</h4>
        <a class="row" href="<?php echo esc_url( home_url( '/join/' ) ); ?>"><span>Join free in under a minute</span><b>→</b></a>
        <p class="note" style="margin:6px 0 0">Rating photos needs no account.</p>
      </div>
    </div>
    <form class="contact-form auth-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <input type="hidden" name="action" value="rp_signin">
      <b class="ttl">Sign in</b>
      <?php if ( ! empty( $_GET['failed'] ) ) : ?><div class="form-msg">That email or username and password do not match.</div><?php elseif ( ! empty( $_GET['empty'] ) ) : ?><div class="form-msg">Both the email or username and the password are needed.</div><?php elseif ( ! empty( $_GET['out'] ) ) : ?><div class="form-msg ok">You are signed out.</div><?php endif; ?>
      <label for="s_login">Email or username<input id="s_login" name="log" type="text" required maxlength="120" autocomplete="username" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['log'] ?? '' ) ) ); ?>"></label>
      <label for="s_pass">Password<input id="s_pass" name="pwd" type="password" required autocomplete="current-password"></label>
      <label class="ck" style="flex-direction:row;align-items:center;gap:8px;font-weight:600"><input type="checkbox" name="rememberme" value="forever" checked style="width:auto;min-height:0">Keep me signed in</label>
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $to ); ?>">
      <input type="hidden" name="rp_signin" value="1">
      <button class="btn btn-orange" type="submit">Sign in</button>
      <small class="note" style="margin:0"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgotten your password?</a></small>
    </form>
  </div>
</div>
<?php get_footer(); ?>
