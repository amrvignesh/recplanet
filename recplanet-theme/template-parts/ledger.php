<?php
/** The ledger feed and the growth chart. Chart data is inlined for main.js. */
$ledger = $args['ledger'] ?? [];
$growth = rp_growth_series();
?>
<section id="ledger" style="background:var(--bg-2)">
  <div class="wrap">
    <div class="section-head">
      <div><div class="eyebrow">The counter, live</div><h2>Every park added moves the number</h2></div>
      <p>The ledger shows exactly why the counter changed. The chart shows every year since 2010.</p>
    </div>
    <div class="ledger">
      <div class="feed" id="feed">
        <?php if ( ! $ledger ) : ?>
          <div><span class="d num">+0.00</span><span>Nothing yet. The next park added appears here.</span><span class="w"></span></div>
        <?php endif; ?>
        <?php foreach ( $ledger as $row ) : $sign = $row['delta_acres'] >= 0 ? '+' : '−'; ?>
          <div><span class="d num"><?php echo esc_html( $sign . RP\format_acres( abs( (float) $row['delta_acres'] ) ) ); ?></span><span><?php echo esc_html( $row['title'] . ( $row['place'] ? ', ' . $row['place'] : '' ) ); ?><?php if ( 'added' !== $row['event'] ) { echo ' <small>(' . esc_html( $row['event'] ) . ')</small>'; } ?></span><span class="w"><?php echo esc_html( wp_date( 'j M, H:i', strtotime( $row['created_at'] . ' UTC' ) ) ); ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="chart">
        <div class="eyebrow" style="margin-bottom:6px">Places on file, by year</div>
        <svg id="growth" viewBox="0 0 520 240" role="img" aria-label="Cumulative places on file by year" data-series="<?php echo esc_attr( wp_json_encode( $growth ) ); ?>"></svg>
        <small>Counted from each record's publish date.</small>
      </div>
    </div>
  </div>
</section>
