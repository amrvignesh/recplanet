<?php
/**
 * The one-minute entry form, in two modes. Handled by inc/upload.php.
 *   contest: any recreational photo (a sunset, a trail, a frog on a log); saying where it was taken is optional.
 *   park:    a photo of one park, shown on that park's page; reached from the park's "Add a photo" button.
 */
$contest = $args['contest'] ?? null;
$park    = $args['park'] ?? null;
$mode    = $park ? 'park' : 'contest';
?>
<div class="upload" id="enter">
  <?php if ( ! is_user_logged_in() ) : ?>
    <b style="font-family:'Bricolage Grotesque';font-size:17px"><?php echo $park ? 'Add a photo of ' . esc_html( get_the_title( $park ) ) : 'Enter in under a minute'; ?></b>
    <p class="note" style="margin:0"><?php echo $park ? 'Join free to add your photo to this park&rsquo;s page.' : 'Any recreational photo can enter: a sunset over a lake, a trail, a bird, a frog on a log. Join free to enter; rating needs no account.'; ?></p>
    <a class="btn btn-lime" href="<?php echo esc_url( wp_registration_url() ); ?>">Join free</a>
    <a class="btn btn-ghost" href="<?php echo esc_url( wp_login_url( $park ? add_query_arg( 'park', $park->ID, home_url( '/contest' ) ) : home_url( '/contest' ) ) ); ?>">Sign in</a>
  <?php elseif ( ! current_user_can( 'rp_enter_contest' ) && ! current_user_can( 'edit_rp_photos' ) ) : ?>
    <p class="note">Your account can't add photos. Ask an editor to add the Member role.</p>
  <?php else : ?>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'rp_upload_photo' ); ?>
      <input type="hidden" name="action" value="rp_upload_photo">
      <input type="hidden" name="kind" value="<?php echo esc_attr( $mode ); ?>">
      <?php if ( $contest && 'contest' === $mode ) : ?><input type="hidden" name="contest_id" value="<?php echo (int) $contest->ID; ?>"><?php endif; ?>
      <?php if ( 'park' === $mode ) : ?>
        <b style="font-family:'Bricolage Grotesque';font-size:17px">Add a photo of <?php echo esc_html( get_the_title( $park ) ); ?></b>
        <p class="note" style="margin:0">It shows on the park&rsquo;s page once an editor has approved it. <a href="<?php echo esc_url( home_url( '/contest/#enter' ) ); ?>">Entering the contest instead?</a></p>
        <input type="hidden" name="park_id" id="park_id" value="<?php echo (int) $park->ID; ?>">
      <?php else : ?>
        <b style="font-family:'Bricolage Grotesque';font-size:17px">Enter in under a minute</b>
        <p class="note" style="margin:0">Any recreational photo counts: a sunset over a lake, a trail, a bird, a frog on a log. It must be taken in a park or other recreational area; saying where is up to you.</p>
      <?php endif; ?>
      <label class="drop" for="photo">Choose a photo<br><small>JPEG or PNG, up to 20 MB.<?php echo 'contest' === $mode ? ' If the photo carries a location, the nearest park is suggested.' : ''; ?></small><input id="photo" type="file" name="photo" accept="image/jpeg,image/png" required style="margin-top:8px"></label>
      <label for="cap">Title<input id="cap" type="text" name="title" placeholder="<?php echo 'park' === $mode ? 'What the photo shows' : 'Sunset over Lake Lanier'; ?>" required maxlength="120"></label>
      <label for="desc">A few words about it <small style="font-weight:500;color:var(--muted)">(optional)</small><textarea id="desc" name="description" rows="3" maxlength="2000" placeholder="<?php echo 'park' === $mode ? 'Which part of the park, what was happening' : 'Where you were, what was happening, what you were thinking'; ?>"></textarea></label>
      <?php if ( 'contest' === $mode ) : ?>
        <label for="tags">Tags <small style="font-weight:500;color:var(--muted)">(optional, comma separated)</small><input id="tags" type="text" name="tags" placeholder="sunset, lake, Georgia" maxlength="200"></label>
        <label for="park_q">Where was it taken? <small style="font-weight:500;color:var(--muted)">(optional)</small><input id="park_q" type="text" name="park_name" placeholder="Start typing a park name" autocomplete="off" list="park_list"><datalist id="park_list"></datalist><input type="hidden" name="park_id" id="park_id" value="0"></label>
      <?php elseif ( $contest ) : ?>
        <label class="ck" style="font-weight:600;font-size:13px"><input type="checkbox" name="also_contest" value="<?php echo (int) $contest->ID; ?>">Also enter it in <?php echo esc_html( get_the_title( $contest ) ); ?></label>
      <?php endif; ?>
      <label for="taken">Taken on <small style="font-weight:500;color:var(--muted)">(optional)</small><input id="taken" type="date" name="taken_on"></label>
      <label class="ck" style="font-weight:600;font-size:13px"><input type="checkbox" name="rights" value="1" required>I took this photo and hold the rights to it. It was taken in a park or other recreational area, and there are no children in it.</label>
      <button class="btn btn-lime" type="submit"><?php echo 'park' === $mode ? 'Add the photo' : 'Enter the contest'; ?></button>
      <small class="note" style="margin:0">An editor approves photos before they show. <a href="<?php echo esc_url( home_url( '/rules' ) ); ?>">The rules.</a></small>
    </form>
  <?php endif; ?>
  <?php if ( ! empty( $_GET['rp_uploaded'] ) ) : ?><div class="notice-ok">Thank you. Your photo is in the queue and will show once an editor approves it.</div><?php endif; ?>
  <?php if ( ! empty( $_GET['rp_error'] ) ) : ?><div class="notice-bad"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['rp_error'] ) ) ); ?></div><?php endif; ?>
</div>
