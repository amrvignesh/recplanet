<?php
/** The growth chart: sixteen years of hand-made records. Used on the About page only. */
$growth = rp_growth_series();
$ledger = RP\Counter::payload()['ledger'];
?>
<div class="ledger">
  <div class="chart">
    <div class="eyebrow" style="margin-bottom:6px">Places on file, by year</div>
    <svg id="growth" viewBox="0 0 520 240" role="img" aria-label="Cumulative places on file by year" data-series="<?php echo esc_attr( wp_json_encode( $growth ) ); ?>"></svg>
    <small>Counted from each record's publish date.</small>
  </div>
  <div class="feed">
    <?php foreach ( array_slice( $ledger, 0, 8 ) as $row ) : $sign = $row['delta_acres'] >= 0 ? '+' : '−'; ?>
      <div><span class="d num"><?php echo esc_html( $sign . RP\format_acres( abs( (float) $row['delta_acres'] ) ) ); ?></span><span><?php echo esc_html( $row['title'] . ( $row['place'] ? ', ' . $row['place'] : '' ) ); ?></span><span class="w"><?php echo esc_html( wp_date( 'j M Y', strtotime( $row['created_at'] . ' UTC' ) ) ); ?></span></div>
    <?php endforeach; ?>
  </div>
</div>
