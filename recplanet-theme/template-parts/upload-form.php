<?php
/** The one-minute entry form. Members only; visitors see the join prompt. Handled by inc/upload.php. */
$contest = $args['contest'] ?? null;
$park    = $args['park'] ?? null;
?>
<div class="upload" id="enter">
  <?php if ( ! is_user_logged_in() ) : ?>
    <b style="font-family:'Bricolage Grotesque';font-size:17px">Enter in under a minute</b>
    <p class="note" style="margin:0">Join free to upload a photo and pin it to the park where you took it. Rating needs no account.</p>
    <a class="btn btn-lime" href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a>
    <a class="btn btn-ghost" href="<?php echo esc_url( wp_login_url( home_url( '/contest' ) ) ); ?>">Sign in</a>
  <?php elseif ( ! current_user_can( 'rp_enter_contest' ) && ! current_user_can( 'edit_rp_photos' ) ) : ?>
    <p class="note">Your account can't enter photos. Ask an editor to add the Member role.</p>
  <?php else : ?>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rp_upload_photo' ); ?>
      <input type="hidden" name="action" value="rp_upload_photo">
      <?php if ( $contest ) : ?><input type="hidden" name="contest_id" value="<?php echo (int) $contest->ID; ?>"><?php endif; ?>
      <b style="font-family:'Bricolage Grotesque';font-size:17px"><?php echo $contest ? 'Enter in under a minute' : 'Add a photo to a park'; ?></b>
      <label class="drop" for="photo">Choose a photo<br><small>JPEG or PNG, up to 20 MB. The photo's location, if it has one, suggests the nearest park.</small><input id="photo" type="file" name="photo" accept="image/jpeg,image/png" required style="margin-top:8px"></label>
      <label for="park_q">Park<input id="park_q" type="text" name="park_name" value="<?php echo esc_attr( $park ? get_the_title( $park ) : '' ); ?>" placeholder="Start typing a park name" autocomplete="off" list="park_list"><datalist id="park_list"></datalist><input type="hidden" name="park_id" id="park_id" value="<?php echo $park ? (int) $park->ID : 0; ?>"></label>
      <label for="cap">Title<input id="cap" type="text" name="title" placeholder="Where, when, what happened" required maxlength="120"></label>
      <label for="taken">Taken on<input id="taken" type="date" name="taken_on"></label>
      <label class="ck" style="font-weight:600;font-size:13px"><input type="checkbox" name="rights" value="1" required>I took this photo and hold the rights to it. It was taken in a park or recreation area, and no children are in it.</label>
      <button class="btn btn-lime" type="submit">Submit for review</button>
      <small class="note" style="margin:0">An editor approves entries before they show. <a href="<?php echo esc_url( home_url( '/contest/rules' ) ); ?>">The rules.</a></small>
    </form>
  <?php endif; ?>
  <?php if ( ! empty( $_GET['rp_uploaded'] ) ) : ?><div class="notice-ok">Thank you. Your photo is in the queue and will show once an editor approves it.</div><?php endif; ?>
  <?php if ( ! empty( $_GET['rp_error'] ) ) : ?><div class="notice-bad"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['rp_error'] ) ) ); ?></div><?php endif; ?>
</div>
