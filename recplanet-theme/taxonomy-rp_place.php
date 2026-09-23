<?php
/** A country, state, county or city. */
get_header();
$t     = get_queried_object();
$level = get_term_meta( $t->term_id, 'rp_level', true );
$stats = rp_place_stats( $t );
[ $where, $args ] = rp_place_where( $t );
$acts    = rp_index_activities( $where, $args );
$largest = rp_index_rows( $where, $args, 'acres DESC', 1 );
$sort    = sanitize_key( $_GET['sort'] ?? 'acres' );
$order   = 'name' === $sort ? 'title ASC' : ( 'checked' === $sort ? 'post_id DESC' : 'acres DESC' );
$paged   = max( 1, (int) get_query_var( 'paged' ) );
$per     = 50;
$rows    = rp_index_rows( $where, $args, $order, $per, ( $paged - 1 ) * $per );
$children = get_terms( [ 'taxonomy' => 'rp_place', 'parent' => $t->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 60 ] );
$bbox    = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT MIN(lat) a, MAX(lat) b, MIN(lng) c, MAX(lng) d FROM " . RP\table( 'park_index' ) . " WHERE ($where) AND lat IS NOT NULL", $args ) );
$chain   = rp_place_chain( $t );
$labels  = [ 'country' => 'Country', 'state' => 'State', 'county' => 'County', 'city' => 'City' ];
$parent  = $t->parent ? get_term( $t->parent, 'rp_place' ) : null;
$lead    = sprintf( '%s public places on file across %s acres%s. %s',
	number_format( $stats['count'] ), RP\format_acres( $stats['acres'], 0 ),
	$largest ? ', from ' . $largest[0]->title . ' at ' . RP\format_acres( (float) $largest[0]->acres, 0 ) . ' acres down' : '',
	$acts ? ucfirst( strtolower( array_key_first( $acts ) ) ) . ' is the most common activity, at ' . number_format( reset( $acts ) ) . ' places. ' : '' ) . 'Every one was checked by a person.';
?>
<?php echo rp_crumbs( rp_place_crumbs( $t ), $parent ? RP\format_acres( $stats['acres'] ) . ' of ' . RP\format_acres( rp_place_stats( $parent )['acres'] ) . ' ' . $parent->name . ' acres' : '' ); ?>
<div class="wrap">
  <div class="phead">
    <div>
      <div class="eyebrow"><?php echo esc_html( $labels[ $level ] ?? 'Place' ); ?><?php if ( $parent ) { echo ' · ' . esc_html( $parent->name ); } ?></div>
      <h1><?php echo esc_html( $t->name ); ?></h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( $lead ); ?></p>
      <?php if ( $acts ) : ?>
      <div class="chips" style="margin-top:18px">
        <?php $i = 0; foreach ( $acts as $name => $n ) : if ( $i++ >= 12 ) break; $at = get_term_by( 'name', $name, 'rp_activity' ); ?>
          <a class="chip" href="<?php echo esc_url( $at ? get_term_link( $at ) : '#' ); ?>"><?php echo esc_html( $name ); ?> <span class="n"><?php echo esc_html( number_format( $n ) ); ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="facts">
      <div><span class="k">Places</span><span class="v num"><?php echo esc_html( number_format( $stats['count'] ) ); ?></span></div>
      <div><span class="k">Acres</span><span class="v num"><?php echo esc_html( RP\format_acres( $stats['acres'], $stats['acres'] > 1e5 ? 0 : 2 ) ); ?></span></div>
      <?php if ( $largest ) : ?><div class="wide"><span class="k">Largest</span><span class="v"><a href="<?php echo esc_url( get_permalink( (int) $largest[0]->post_id ) ); ?>"><?php echo esc_html( $largest[0]->title ); ?></a></span></div><?php endif; ?>
      <div class="wide actions"><a class="btn btn-lime" href="<?php echo esc_url( add_query_arg( [ 'place' => $t->term_id ], home_url( '/atlas' ) ) ); ?>">See them on the map</a></div>
    </div>
  </div>

  <?php if ( $bbox && $bbox->a && $level !== 'country' ) : $clat = ( $bbox->a + $bbox->b ) / 2; $clng = ( $bbox->c + $bbox->d ) / 2; $span = max( $bbox->b - $bbox->a, ( $bbox->d - $bbox->c ) * 0.8 ); $zoom = $span > 4 ? 6 : ( $span > 1.5 ? 8 : ( $span > 0.5 ? 10 : ( $span > 0.15 ? 12 : 13 ) ) ); ?>
  <div class="pmap wide-map"><?php echo rp_embed_map( $clat, $clng, $t->name . ' on the map', 'view', $zoom ); ?><span class="pill"><?php echo esc_html( number_format( $stats['count'] ) ); ?> places · open the atlas for every dot</span><a class="btn btn-white open" href="<?php echo esc_url( add_query_arg( [ 'place' => $t->term_id ], home_url( '/atlas' ) ) ); ?>">Open in atlas</a></div>
  <?php endif; ?>

  <section class="two" style="padding-top:34px">
    <div>
      <div class="sortbar"><span class="lbl">Sort by</span>
        <?php foreach ( [ 'acres' => 'Acres', 'name' => 'Name', 'checked' => 'Recently added' ] as $k => $lbl ) : ?>
          <a class="<?php echo $sort === $k ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sort', $k, get_term_link( $t ) ) ); ?>"><?php echo esc_html( $lbl ); ?></a>
        <?php endforeach; ?>
      </div>
      <div class="plist">
        <div class="phd"><span>Place</span><span>Activities</span><span style="text-align:right">Acres</span></div>
        <?php foreach ( $rows as $r ) { echo rp_park_row( $r ); } ?>
        <?php if ( ! $rows ) : ?><div class="prow"><span class="ph">Nothing on file here yet.</span></div><?php endif; ?>
      </div>
      <?php $pages = (int) ceil( $stats['count'] / $per ); if ( $pages > 1 ) : ?>
      <nav class="pager" aria-label="Pages">
        <?php if ( $paged > 1 ) : ?><a href="<?php echo esc_url( rp_page_link( get_term_link( $t ), $paged - 1, $sort ) ); ?>">← Previous</a><?php endif; ?>
        <span>Page <?php echo (int) $paged; ?> of <?php echo (int) $pages; ?></span>
        <?php if ( $paged < $pages ) : ?><a href="<?php echo esc_url( rp_page_link( get_term_link( $t ), $paged + 1, $sort ) ); ?>">Next →</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
    <div class="side">
      <?php
      $rows_r = [];
      foreach ( [ 'country', 'state', 'county', 'city' ] as $lv ) {
        if ( isset( $chain['terms'][ $lv ] ) ) { $ct = $chain['terms'][ $lv ]; $cs = rp_place_stats( $ct ); $rows_r[] = [ $ct->name, $cs['acres'], get_term_link( $ct ), $ct->term_id === $t->term_id ]; }
      }
      echo rp_acre_ribbon( $rows_r, true );
      ?>
      <?php if ( $children ) : ?>
      <div class="box"><h4><?php echo esc_html( 'state' === $level ? 'Cities and counties' : ( 'country' === $level ? 'States and provinces' : 'Within' ) ); ?></h4>
        <?php foreach ( array_slice( $children, 0, 15 ) as $c ) : ?><a class="row" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><span><?php echo esc_html( $c->name ); ?></span><b class="num"><?php echo esc_html( number_format( $c->count ) ); ?></b></a><?php endforeach; ?>
        <?php if ( count( $children ) > 15 ) : ?><details class="morelist"><summary>All <?php echo count( $children ); ?></summary><?php foreach ( array_slice( $children, 15 ) as $c ) : ?><a class="row" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><span><?php echo esc_html( $c->name ); ?></span><b class="num"><?php echo esc_html( number_format( $c->count ) ); ?></b></a><?php endforeach; ?></details><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php get_footer(); ?>
