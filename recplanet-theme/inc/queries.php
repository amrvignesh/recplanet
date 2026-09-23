<?php
/**
 * Reads against the plugin's index and roll-up tables. Nothing here touches postmeta in a loop.
 */

defined( 'ABSPATH' ) || exit;

/** Rows from rp_park_index. $where uses column names; $args are for $wpdb->prepare. */
function rp_index_rows( string $where, array $args = [], string $order = 'acres DESC', int $limit = 50, int $offset = 0 ): array {
	global $wpdb;
	$sql = "SELECT post_id, title, lat, lng, acres, country, state, county, city, steward_level, activity_bits, has_photo, published_year FROM " . RP\table( 'park_index' ) . " WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset";
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql );
}

/** Count and acres for a WHERE. */
function rp_index_sum( string $where, array $args = [] ): array {
	global $wpdb;
	$sql = "SELECT COUNT(*) AS n, COALESCE(SUM(acres),0) AS a FROM " . RP\table( 'park_index' ) . " WHERE $where";
	$r   = $wpdb->get_row( $args ? $wpdb->prepare( $sql, $args ) : $sql );
	return [ 'count' => (int) $r->n, 'acres' => (float) $r->a ];
}

/** Activity counts inside a WHERE, for chips. Returns [ name => count ] sorted by count. */
function rp_index_activities( string $where, array $args = [] ): array {
	global $wpdb;
	$out = [];
	foreach ( RP\ACTIVITIES as $i => $name ) {
		$sql = "SELECT COUNT(*) FROM " . RP\table( 'park_index' ) . " WHERE ($where) AND (activity_bits & %d) <> 0";
		$n   = (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( $args, [ 1 << $i ] ) ) );
		if ( $n ) {
			$out[ $name ] = $n;
		}
	}
	arsort( $out );
	return $out;
}

/** The WHERE clause and args that describe a place term. */
function rp_place_where( WP_Term $t ): array {
	$chain = rp_place_chain( $t );
	$c     = $chain['country'] ?? null;
	$s     = $chain['state'] ?? null;
	$level = get_term_meta( $t->term_id, 'rp_level', true );
	switch ( $level ) {
		case 'country': return [ 'country = %s', [ $c ] ];
		case 'state':   return [ 'country = %s AND state = %s', [ $c, $s ] ];
		case 'county':  return [ 'country = %s AND state = %s AND county = %s', [ $c, $s, $t->name ] ];
		case 'city':    return [ 'country = %s AND state = %s AND city = %s', [ $c, $s, $t->name ] ];
	}
	return [ '1=0', [] ];
}

/** Codes up the chain: [country => 'us', state => 'TX', county => name, city => name] plus the terms. */
function rp_place_chain( WP_Term $t ): array {
	$out = [ 'terms' => [] ];
	$cur = $t;
	while ( $cur && ! is_wp_error( $cur ) ) {
		$level = get_term_meta( $cur->term_id, 'rp_level', true );
		$code  = get_term_meta( $cur->term_id, 'rp_code', true );
		$out[ $level ] = in_array( $level, [ 'country', 'state' ], true ) ? $code : $cur->name;
		$out['terms'][ $level ] = $cur;
		$cur = $cur->parent ? get_term( $cur->parent, 'rp_place' ) : null;
	}
	return $out;
}

/** Count and acres for a place, from the roll-up table. */
function rp_place_stats( WP_Term $t ): array {
	$chain = rp_place_chain( $t );
	$level = get_term_meta( $t->term_id, 'rp_level', true );
	$c     = $chain['country'] ?? '';
	$s     = $chain['state'] ?? '';
	switch ( $level ) {
		case 'country': return RP\Counter::get( 'country', $c );
		case 'state':   return RP\Counter::get( 'state', "$c/$s" );
		case 'county':  return RP\Counter::get( 'county', "$c/$s/" . RP\legacy_slug( $t->name ) );
		case 'city':    return RP\Counter::get( 'city', "$c/$s/" . RP\legacy_slug( $t->name ) );
	}
	return [ 'count' => 0, 'acres' => 0 ];
}

/** Find a place term from a park's meta. */
function rp_park_place_term( int $park_id ): ?WP_Term {
	$terms = wp_get_object_terms( $park_id, 'rp_place' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
}

/** Photos pinned to a park. */
function rp_park_photos( int $park_id, int $limit = 8 ): array {
	return get_posts( [ 'post_type' => 'rp_photo', 'posts_per_page' => $limit, 'post_status' => 'publish', 'meta_key' => 'rp_park_id', 'meta_value' => $park_id, 'orderby' => 'meta_value_num', 'meta_key_order' => 'rp_votes', 'order' => 'DESC' ] );
}

/** Growth series for the chart, [year => places]. */
function rp_growth_series(): array {
	$out = [];
	foreach ( RP\Counter::growth() as $r ) {
		$out[ (int) $r['y'] ] = (int) $r['n'];
	}
	return $out;
}
