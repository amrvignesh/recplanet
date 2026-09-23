<?php
/**
 * Template Name: Acre Counter
 * The old site's Acre Counter page, rebuilt: the world total, then any country, then any state, summed live.
 */
get_header();
global $wpdb;
$idx    = RP\table( 'park_index' );
$world  = RP\Counter::get( 'world', 'world' );
$sel_c  = strtolower( sanitize_text_field( $_GET['country'] ?? '' ) );
$sel_s  = strtoupper( sanitize_text_field( $_GET['state'] ?? '' ) );
$countries = $wpdb->get_results( "SELECT country, COUNT(*) n, COALESCE(SUM(acres),0) a FROM $idx WHERE country <> '' GROUP BY country ORDER BY a DESC" );
$names = [];
foreach ( get_terms( [ 'taxonomy' => 'rp_place', 'hide_empty' => false, 'meta_key' => 'rp_level', 'meta_value' => 'country', 'number' => 300 ] ) as $t ) {
	$names[ $t->slug ] = $t->name;
}
$states = $sel_c ? $wpdb->get_results( $wpdb->prepare( "SELECT state, COUNT(*) n, COALESCE(SUM(acres),0) a FROM $idx WHERE country = %s AND state <> '' GROUP BY state ORDER BY a DESC", $sel_c ) ) : [];
$shown  = $world;
$label  = 'the world';
if ( $sel_c && $sel_s ) {
	$shown = RP\Counter::get( 'state', "$sel_c/$sel_s" );
	$label = ( 'us' === $sel_c ? ( RP\us_states()[ $sel_s ] ?? $sel_s ) : $sel_s ) . ', ' . ( $names[ $sel_c ] ?? strtoupper( $sel_c ) );
} elseif ( $sel_c ) {
	$shown = RP\Counter::get( 'country', $sel_c );
	$label = $names[ $sel_c ] ?? strtoupper( $sel_c );
}
$whole = floor( $shown['acres'] );
$dec   = (int) round( ( $shown['acres'] - $whole ) * 100 );
$rows  = $sel_c && $sel_s ? rp_index_rows( 'country = %s AND state = %s', [ $sel_c, $sel_s ], 'acres DESC', 100 ) : [];
?>
<div class="wrap">
  <div class="phead one">
    <div>
      <div class="eyebrow">Acre Counter</div>
      <h1>The one big number that goes up</h1>
      <p class="lead" style="margin-top:14px">This is the acre counter. It counts the acres of public land from the website's database. You can see the world total, pick a country, and narrow it to a state or province. The world has too many negative counters, world population, national debt. It is time to have a positive one. The figure includes public land that is not strictly recreational, such as military bases.</p>
    </div>
  </div>

  <section class="counter-page" style="padding-top:0">
    <div class="counter-card">
      <div class="eyebrow" style="color:var(--lime)">Acres of public land in <?php echo esc_html( $label ); ?></div>
      <div class="counter num"><span class="big"><?php echo esc_html( number_format( $whole ) ); ?></span><span class="dec">.<?php echo esc_html( str_pad( (string) $dec, 2, '0', STR_PAD_LEFT ) ); ?></span></div>
      <div class="counter-sub"><span><b><?php echo esc_html( number_format( $shown['count'] ) ); ?></b> places</span><?php if ( $shown['acres'] > 0 ) : ?><span><?php echo esc_html( RP\Human_Scale::describe( $shown['acres'] ) ); ?></span><?php endif; ?></div>
      <form class="counter-form" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
        <label for="country">Country<select id="country" name="country"><option value="">The world</option>
          <?php foreach ( $countries as $c ) : ?><option value="<?php echo esc_attr( $c->country ); ?>"<?php selected( $sel_c, $c->country ); ?>><?php echo esc_html( $names[ $c->country ] ?? strtoupper( $c->country ) ); ?></option><?php endforeach; ?>
        </select></label>
        <?php if ( $states ) : ?>
        <label for="state">State or province<select id="state" name="state"><option value="">All of <?php echo esc_html( $names[ $sel_c ] ?? strtoupper( $sel_c ) ); ?></option>
          <?php foreach ( $states as $s ) : ?><option value="<?php echo esc_attr( $s->state ); ?>"<?php selected( $sel_s, $s->state ); ?>><?php echo esc_html( 'us' === $sel_c ? ( RP\us_states()[ $s->state ] ?? $s->state ) : $s->state ); ?></option><?php endforeach; ?>
        </select></label>
        <?php endif; ?>
        <button class="btn btn-orange" type="submit">Count</button>
      </form>
    </div>
  </section>

  <section style="padding-top:0">
    <?php if ( $sel_c && $sel_s ) : ?>
      <div class="section-head"><div><div class="eyebrow">Largest first</div><h2><?php echo esc_html( $label ); ?></h2></div><a class="btn btn-ghost" href="<?php echo esc_url( 'us' === $sel_c ? home_url( '/' . strtolower( $sel_s ) . '/' ) : home_url( '/world/' . $sel_c . '/' ) ); ?>">Open the state page</a></div>
      <div class="plist"><div class="phd"><span>Place</span><span>Activities</span><span style="text-align:right">Acres</span></div><?php foreach ( $rows as $r ) { echo rp_park_row( $r ); } ?></div>
      <p class="note">The first 100 places. Every acre in the figure above is one of these, or one of the rest on the state page.</p>
    <?php elseif ( $sel_c ) : ?>
      <div class="section-head"><div><div class="eyebrow">By state or province</div><h2><?php echo esc_html( $label ); ?></h2></div></div>
      <div class="plist"><div class="phd"><span>State</span><span>Places</span><span style="text-align:right">Acres</span></div>
        <?php foreach ( $states as $s ) : ?><a class="prow" href="<?php echo esc_url( add_query_arg( [ 'country' => $sel_c, 'state' => $s->state ], get_permalink() ) ); ?>"><span class="pname"><?php echo esc_html( 'us' === $sel_c ? ( RP\us_states()[ $s->state ] ?? $s->state ) : $s->state ); ?></span><span class="pacts"><span><?php echo esc_html( number_format( $s->n ) ); ?> places</span></span><span class="pac num"><?php echo esc_html( RP\format_acres( (float) $s->a ) ); ?></span></a><?php endforeach; ?>
        <div class="prow total"><span class="pname">Total</span><span class="pacts"><span><?php echo esc_html( number_format( $shown['count'] ) ); ?> places</span></span><span class="pac num"><?php echo esc_html( RP\format_acres( $shown['acres'] ) ); ?></span></div>
      </div>
    <?php else : ?>
      <div class="section-head"><div><div class="eyebrow">By country</div><h2>Where the acres are</h2></div><p>Antarctica is on file as one record and holds most of the total; the United States holds most of the places.</p></div>
      <div class="plist"><div class="phd"><span>Country</span><span>Places</span><span style="text-align:right">Acres</span></div>
        <?php foreach ( $countries as $c ) : ?><a class="prow" href="<?php echo esc_url( add_query_arg( 'country', $c->country, get_permalink() ) ); ?>"><span class="pname"><?php echo esc_html( $names[ $c->country ] ?? strtoupper( $c->country ) ); ?></span><span class="pacts"><span><?php echo esc_html( number_format( $c->n ) ); ?> places</span></span><span class="pac num"><?php echo esc_html( RP\format_acres( (float) $c->a ) ); ?></span></a><?php endforeach; ?>
        <div class="prow total"><span class="pname">Total</span><span class="pacts"><span><?php echo esc_html( number_format( $world['count'] ) ); ?> places</span></span><span class="pac num"><?php echo esc_html( RP\format_acres( $world['acres'] ) ); ?></span></div>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php get_footer(); ?>
