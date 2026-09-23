<?php
/** Newest places: the last additions, framed as content rather than bookkeeping. */
$rows = rp_index_rows( 'lat IS NOT NULL', [], 'post_id DESC', 6 );
?>
<section id="ledger" style="background:var(--bg-2)">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">Newest places</div><h2>Just added to the map</h2></div>
      <p>New places go in every week, each one checked in person. These are the latest.</p>
    </div>
    <div class="cards">
      <?php foreach ( $rows as $r ) { echo rp_park_card( $r, $r->city ? $r->city . ( $r->state ? ', ' . $r->state : '' ) : ( RP\us_states()[ $r->state ] ?? $r->state ) ); } ?>
    </div>
  </div>
</section>
