<?php
/** Activity, facility and steward pages: "Where to fish", "Playgrounds", "City of Irving". */
get_header();
$t   = get_queried_object();
$tax = $t->taxonomy;
global $wpdb;
$idx = RP\table( 'park_index' );
if ( 'rp_activity' === $tax ) {
	$i = array_search( $t->name, RP\ACTIVITIES, true );
	$where = '(activity_bits & %d) <> 0';
	$args  = [ 1 << max( 0, (int) $i ) ];
	$title = 'Where to ' . rp_activity_verb( $t->name );
} elseif ( 'rp_steward' === $tax ) {
	$where = 'steward_term = %d';
	$args  = [ $t->term_id ];
	if ( ! $t->parent ) {
		$where = 'steward_level = %s';
		$args  = [ $t->slug ];
	}
	$title = 'Managed by ' . $t->name;
} else {
	$ids   = get_objects_in_term( $t->term_id, $tax );
	$where = $ids ? 'post_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : '1=0';
	$args  = [];
	$title = ucfirst( $t->name );
}
$stats  = rp_index_sum( $where, $args );
$sort   = sanitize_key( $_GET['sort'] ?? 'name' );
$order  = 'acres' === $sort ? 'acres DESC' : ( 'city' === $sort ? 'state ASC, city ASC' : 'title ASC' );
$paged  = max( 1, (int) get_query_var( 'paged' ) );
$per    = 50;
$rows   = rp_index_rows( $where, $args, $order, $per, ( $paged - 1 ) * $per );
$states = $wpdb->get_results( $wpdb->prepare( "SELECT state, COUNT(*) n FROM $idx WHERE ($where) AND country='us' AND state<>'' GROUP BY state ORDER BY n DESC LIMIT 12", $args ) );
?>
<?php echo rp_crumbs( [ [ 'World', home_url( '/' ) ], [ ucfirst( str_replace( 'rp_', '', $tax ) ), home_url( '/' . ( 'rp_activity' === $tax ? 'activities' : ( 'rp_facility' === $tax ? 'facilities' : 'managed-by' ) ) ) ], [ $t->name, get_term_link( $t ) ] ] ); ?>
<div class="wrap">
  <div class="phead">
    <div>
      <div class="eyebrow"><?php echo esc_html( 'rp_activity' === $tax ? 'Activity' : ( 'rp_facility' === $tax ? 'Facility' : 'Steward' ) ); ?></div>
      <h1><?php echo esc_html( $title ); ?></h1>
      <p class="lead" style="margin-top:14px"><?php echo esc_html( number_format( $stats['count'] ) ); ?> places across <?php echo esc_html( RP\format_acres( $stats['acres'], 0 ) ); ?> acres, each one checked by a person.<?php if ( $t->description ) { echo ' ' . esc_html( $t->description ); } ?></p>
      <?php if ( $states ) : ?>
      <div class="chips" style="margin-top:18px">
        <?php foreach ( $states as $s ) : ?><a class="chip" href="<?php echo esc_url( home_url( '/' . strtolower( $s->state ) ) ); ?>"><?php echo esc_html( RP\us_states()[ $s->state ] ?? $s->state ); ?> <span class="n"><?php echo esc_html( number_format( $s->n ) ); ?></span></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="facts">
      <div><span class="k">Places</span><span class="v num"><?php echo esc_html( number_format( $stats['count'] ) ); ?></span></div>
      <div><span class="k">Acres between them</span><span class="v num"><?php echo esc_html( RP\format_acres( $stats['acres'], 0 ) ); ?></span></div>
      <div class="wide actions"><a class="btn btn-lime" href="<?php echo esc_url( add_query_arg( [ 'rp_activity' === $tax ? 'activity' : 'term' => $t->slug ], home_url( '/atlas' ) ) ); ?>">See them on the map</a></div>
    </div>
  </div>
  <section class="two" style="padding-top:10px">
    <div>
      <div class="sortbar"><span class="lbl">Sort by</span>
        <?php foreach ( [ 'name' => 'Name', 'acres' => 'Acres', 'city' => 'Place' ] as $k => $lbl ) : ?><a class="<?php echo $sort === $k ? 'on' : ''; ?>" href="<?php echo esc_url( 'name' === $k ? get_term_link( $t ) : add_query_arg( 'sort', $k, get_term_link( $t ) ) ); ?>"><?php echo esc_html( $lbl ); ?></a><?php endforeach; ?>
      </div>
      <div class="plist"><div class="phd"><span>Place</span><span>Activities</span><span style="text-align:right">Acres</span></div><?php foreach ( $rows as $r ) { echo rp_park_row( $r ); } ?></div>
      <?php $pages = (int) ceil( $stats['count'] / $per ); if ( $pages > 1 ) : ?>
      <nav class="pager" aria-label="Pages"><?php if ( $paged > 1 ) : ?><a href="<?php echo esc_url( rp_page_link( get_term_link( $t ), $paged - 1, $sort ) ); ?>">← Previous</a><?php endif; ?><span>Page <?php echo (int) $paged; ?> of <?php echo (int) $pages; ?></span><?php if ( $paged < $pages ) : ?><a href="<?php echo esc_url( rp_page_link( get_term_link( $t ), $paged + 1, $sort ) ); ?>">Next →</a><?php endif; ?></nav>
      <?php endif; ?>
    </div>
    <div class="side">
      <?php if ( 'rp_activity' === $tax ) : ?>
      <div class="box"><h4>Other activities</h4>
        <?php foreach ( get_terms( [ 'taxonomy' => 'rp_activity', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'exclude' => [ $t->term_id ] ] ) as $o ) : ?><a class="row" href="<?php echo esc_url( get_term_link( $o ) ); ?>"><span><?php echo esc_html( $o->name ); ?></span><b class="num"><?php echo esc_html( number_format( $o->count ) ); ?></b></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="box dashed"><b>Why this page exists</b><p>People don't search for a directory. They search for <?php echo esc_html( strtolower( $title ) ); ?> near them. Every activity, facility and steward gets a page like this, crossed with every place.</p></div>
    </div>
  </section>
</div>
<?php get_footer(); ?>
