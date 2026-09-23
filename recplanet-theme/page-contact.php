<?php
/**
 * Template Name: Contact
 * The contact form: stored in the site's inbox, emailed to the owner, guarded by a captcha.
 */
get_header();
$park = ! empty( $_GET['park'] ) ? get_post( (int) $_GET['park'] ) : null;
$pre  = sanitize_key( $_GET['about'] ?? '' );
$site = get_option( 'rp_turnstile_site', '' );
$chal = $site ? null : RP\Messages::challenge();
?>
<div class="wrap">
  <div class="phead">
    <div>
      <div class="eyebrow">Contact</div>
      <h1><?php the_title(); ?></h1>
      <div class="lead entry" style="margin-top:14px"><?php the_post(); the_content(); ?></div>
      <div class="contact-reasons">
        <a class="chip<?php echo 'missing' === $pre ? ' on' : ''; ?>" href="?about=missing">A park is missing</a>
        <a class="chip<?php echo 'changed' === $pre ? ' on' : ''; ?>" href="?about=changed">Something changed</a>
        <a class="chip<?php echo 'prize' === $pre ? ' on' : ''; ?>" href="?about=prize">Prize claim code</a>
        <a class="chip<?php echo 'photo' === $pre ? ' on' : ''; ?>" href="?about=photo">About a photo</a>
        <a class="chip<?php echo 'other' === $pre ? ' on' : ''; ?>" href="?about=other">Something else</a>
      </div>
      <div class="box" style="margin-top:22px"><h4>Other ways</h4>
        <a class="row" href="https://www.facebook.com/pages/RecPlanet/127298557845" rel="noopener" target="_blank"><span>RecPlanet on Facebook</span><b>→</b></a>
        <a class="row" href="<?php echo esc_url( home_url( '/contest/' ) ); ?>"><span>Add a photo to a park instead</span><b>→</b></a>
        <p class="note" style="margin:6px 0 0">Every message reaches Taylor directly. Replies come from the address you give.</p>
      </div>
    </div>
    <form class="contact-form" id="contactForm" novalidate>
      <b class="ttl">Send a message</b>
      <div class="grid2">
        <label for="c_name">Your name<input id="c_name" name="name" type="text" required maxlength="120" autocomplete="name"></label>
        <label for="c_email">Your email<input id="c_email" name="email" type="email" required maxlength="120" autocomplete="email"></label>
      </div>
      <label for="c_subject">What is it about
        <select id="c_subject" name="subject">
          <option value="A park is missing"<?php selected( $pre, 'missing' ); ?>>A park is missing</option>
          <option value="Something changed at a park"<?php selected( $pre, 'changed' ); ?>>Something changed at a park</option>
          <option value="Prize claim code"<?php selected( $pre, 'prize' ); ?>>Prize claim code</option>
          <option value="About a photo"<?php selected( $pre, 'photo' ); ?>>About a photo</option>
          <option value="Something else"<?php selected( in_array( $pre, [ 'other', '' ], true ), true ); ?>>Something else</option>
        </select>
      </label>
      <label for="c_park">Park, if there is one<input id="c_park" type="text" name="park_name" value="<?php echo esc_attr( $park ? get_the_title( $park ) : '' ); ?>" placeholder="Start typing a park name" autocomplete="off" list="park_list"><datalist id="park_list"></datalist><input type="hidden" name="park_id" id="park_id" value="<?php echo $park ? (int) $park->ID : 0; ?>"></label>
      <label for="c_msg">Message<textarea id="c_msg" name="message" rows="6" required minlength="10" maxlength="5000" placeholder="The more detail the better: the park's name and city, what you found, when you were there."></textarea></label>
      <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <input type="hidden" name="started" value="<?php echo (int) time(); ?>">
      <?php if ( $site ) : ?>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site ); ?>" data-theme="light"></div>
      <?php else : ?>
        <label for="c_answer" class="captcha"><span><?php echo esc_html( $chal['question'] ); ?></span><input id="c_answer" name="answer" type="text" inputmode="numeric" required autocomplete="off" style="max-width:90px"><input type="hidden" name="token" value="<?php echo esc_attr( $chal['token'] ); ?>"><small>A quick sum keeps the robots out.</small></label>
      <?php endif; ?>
      <button class="btn btn-orange" type="submit">Send message</button>
      <div class="form-msg" id="contactMsg" hidden></div>
    </form>
  </div>
</div>
<?php if ( $site ) : ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<?php get_footer(); ?>
