<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * /wp-json/recplanet/v1/counter        the counter, ledger and growth series
 * /wp-json/recplanet/v1/places         parks in a bounding box, filtered, for the atlas
 * /wp-json/recplanet/v1/near           parks by distance from a point
 * /wp-json/recplanet/v1/stewards       type-ahead over the stewards for the atlas's Managed by box
 * /wp-json/recplanet/v1/embed.js       the one-line embeddable counter
 */
class Rest {

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route( 'recplanet/v1', '/counter', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'counter' ], 'permission_callback' => '__return_true' ] );
		register_rest_route( 'recplanet/v1', '/places', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'places' ], 'permission_callback' => '__return_true', 'args' => [
			'bbox'     => [ 'required' => true, 'description' => 'west,south,east,north' ],
			'activity' => [ 'default' => '', 'description' => 'one activity name or a comma list' ],
			'activity_mode' => [ 'default' => '', 'description' => 'empty: any of the activities; all: every one of them' ],
			'steward'  => [ 'default' => '' ],
			'min_acres'=> [ 'default' => 0 ],
			'year'     => [ 'default' => 0 ],
			'no_photo' => [ 'default' => 0 ],
			'limit'    => [ 'default' => 2000 ],
		] ] );
		register_rest_route( 'recplanet/v1', '/near', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'near' ], 'permission_callback' => '__return_true', 'args' => [
			'lat' => [ 'required' => true ], 'lng' => [ 'required' => true ], 'km' => [ 'default' => 2 ], 'activity' => [ 'default' => '' ], 'activity_mode' => [ 'default' => '' ], 'steward' => [ 'default' => '' ], 'limit' => [ 'default' => 20 ],
		] ] );
		register_rest_route( 'recplanet/v1', '/stewards', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'stewards' ], 'permission_callback' => '__return_true', 'args' => [
			'q'     => [ 'default' => '', 'description' => 'part of a steward name; empty lists who manages the most places in the bbox' ],
			'bbox'  => [ 'default' => '', 'description' => 'west,south,east,north: stewards with places here rank first' ],
			'limit' => [ 'default' => 8 ],
		] ] );
	}

	public static function counter( \WP_REST_Request $r ): \WP_REST_Response {
		$p = Counter::payload();
		$p['growth'] = Counter::growth();
		$res = new \WP_REST_Response( $p );
		$res->header( 'Cache-Control', 'public, max-age=300' );
		$origins = array_filter( array_map( 'trim', explode( "\n", (string) get_option( 'rp_embed_origins', '' ) ) ) );
		$origin  = $r->get_header( 'origin' );
		if ( ! $origins || ( $origin && in_array( rtrim( $origin, '/' ), $origins, true ) ) ) {
			$res->header( 'Access-Control-Allow-Origin', $origin ?: '*' );
		}
		return $res;
	}

	private static function filters( \WP_REST_Request $r ): array {
		global $wpdb;
		$w = [];
		$a = [];
		// activity: one name or a comma list. Places with any of them; activity_mode=all asks for places with every one.
		$mask  = 0;
		$lower = array_map( 'strtolower', ACTIVITIES );
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $r['activity'] ) ) ) as $name ) {
			$i = array_search( strtolower( $name ), $lower, true );
			if ( false !== $i ) {
				$mask |= 1 << $i;
			}
		}
		if ( $mask ) {
			if ( 'all' === (string) $r['activity_mode'] ) {
				$w[] = '(activity_bits & %d) = %d';
				array_push( $a, $mask, $mask );
			} else {
				$w[] = '(activity_bits & %d) <> 0';
				$a[] = $mask;
			}
		}
		// steward: a comma list of level slugs (city, federal ...) and/or steward term ids, any of which may match.
		if ( '' !== (string) $r['steward'] ) {
			$parts  = explode( ',', (string) $r['steward'] );
			$levels = array_values( array_intersect( $parts, array_keys( STEWARD_LEVELS ) ) );
			$terms  = array_values( array_map( 'intval', array_filter( $parts, 'ctype_digit' ) ) );
			$or     = [];
			if ( $levels ) {
				$or[] = 'steward_level IN (' . implode( ',', array_fill( 0, count( $levels ), '%s' ) ) . ')';
				$a    = array_merge( $a, $levels );
			}
			if ( $terms ) {
				$or[] = 'steward_term IN (' . implode( ',', array_fill( 0, count( $terms ), '%d' ) ) . ')';
				$a    = array_merge( $a, $terms );
			}
			if ( $or ) {
				$w[] = '(' . implode( ' OR ', $or ) . ')';
			}
		}
		if ( (float) $r['min_acres'] > 0 ) {
			$w[] = 'acres >= %f';
			$a[] = (float) $r['min_acres'];
		}
		if ( (int) $r['year'] > 0 ) {
			$w[] = 'published_year <= %d';
			$a[] = (int) $r['year'];
		}
		if ( (int) $r['no_photo'] ) {
			$w[] = 'has_photo = 0';
		}
		return [ $w, $a ];
	}

	public static function places( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$b = array_map( 'floatval', explode( ',', $r['bbox'] ) );
		if ( 4 !== count( $b ) ) {
			return new \WP_REST_Response( [ 'error' => 'bbox must be west,south,east,north' ], 400 );
		}
		[ $w, $a ] = self::filters( $r );
		$w[] = 'lng BETWEEN %f AND %f AND lat BETWEEN %f AND %f';
		array_push( $a, $b[0], $b[2], $b[1], $b[3] );
		$limit = min( 5000, max( 1, (int) $r['limit'] ) );
		$sql   = "SELECT post_id, title, lat, lng, acres, city, state, steward_level, activity_bits, has_photo FROM " . table( 'park_index' ) . " WHERE " . implode( ' AND ', $w ) . " ORDER BY acres DESC LIMIT $limit";
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, $a ), ARRAY_A );
		$sum   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(acres),0) AS a FROM " . table( 'park_index' ) . " WHERE " . implode( ' AND ', $w ), $a ) );
		$res   = new \WP_REST_Response( [
			'in_view' => [ 'count' => (int) $sum->n, 'acres' => (float) $sum->a ],
			'places'  => array_map( fn( $p ) => [ 'id' => (int) $p['post_id'], 't' => $p['title'], 'lat' => (float) $p['lat'], 'lng' => (float) $p['lng'], 'ac' => null === $p['acres'] ? null : (float) $p['acres'], 'city' => $p['city'], 'st' => $p['state'], 'sw' => $p['steward_level'], 'bits' => (int) $p['activity_bits'], 'ph' => (int) $p['has_photo'], 'url' => get_permalink( (int) $p['post_id'] ) ], $rows ),
		] );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	/**
	 * Suggestions for the atlas's Managed by box. Stewards whose name contains q, counted from the park index so the
	 * numbers agree with the map; those with places inside the bbox come first. With an empty q the list is simply who
	 * manages the most places in view. The six levels are appended: all of them for an empty q, otherwise the ones
	 * whose label matches.
	 */
	public static function stewards( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$q     = trim( (string) $r['q'] );
		$limit = min( 25, max( 1, (int) $r['limit'] ) );
		$b     = array_map( 'floatval', array_filter( explode( ',', (string) $r['bbox'] ), 'is_numeric' ) );
		$a     = [];
		$here  = '0';
		if ( 4 === count( $b ) ) {
			$here = 'SUM(pi.lng BETWEEN %f AND %f AND pi.lat BETWEEN %f AND %f)';
			array_push( $a, $b[0], $b[2], $b[1], $b[3] );
		}
		$where = 'tt.taxonomy = %s AND tt.parent <> 0';
		$a[]   = TAX_STEWARD;
		if ( '' !== $q ) {
			$where .= ' AND t.name LIKE %s';
			$a[]    = '%' . $wpdb->esc_like( $q ) . '%';
		}
		$sql  = "SELECT t.term_id AS id, t.name, tt.parent, COUNT(pi.post_id) AS n, $here AS here
			FROM {$wpdb->terms} t
			JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			LEFT JOIN " . table( 'park_index' ) . " pi ON pi.steward_term = t.term_id
			WHERE $where
			GROUP BY t.term_id, t.name, tt.parent
			HAVING n > 0" . ( '' === $q ? ' AND here > 0' : '' ) . "
			ORDER BY here DESC, n DESC, t.name ASC
			LIMIT $limit";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $a ), ARRAY_A );
		$lv   = [];
		foreach ( get_terms( [ 'taxonomy' => TAX_STEWARD, 'parent' => 0, 'hide_empty' => false ] ) as $t ) {
			$lv[ $t->term_id ] = $t->slug;
		}
		$items = array_map( fn( $s ) => [ 'type' => 'term', 'id' => (int) $s['id'], 'label' => $s['name'], 'level' => $lv[ (int) $s['parent'] ] ?? 'other', 'n' => (int) $s['n'], 'here' => (int) $s['here'] ], $rows );
		foreach ( STEWARD_LEVELS as $slug => $label ) {
			if ( '' === $q || false !== stripos( $label, $q ) || 0 === stripos( $slug, $q ) ) {
				$items[] = [ 'type' => 'level', 'key' => $slug, 'label' => $label ];
			}
		}
		$res = new \WP_REST_Response( [ 'items' => $items ] );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	public static function near( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$lat = (float) $r['lat'];
		$lng = (float) $r['lng'];
		$km  = min( 100, max( 0.2, (float) $r['km'] ) );
		[ $w, $a ] = self::filters( $r );
		$dlat = $km / 111.0;
		$dlng = $km / ( 111.0 * max( 0.1, cos( deg2rad( $lat ) ) ) );
		$w[]  = 'lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f';
		array_push( $a, $lat - $dlat, $lat + $dlat, $lng - $dlng, $lng + $dlng );
		$limit = min( 100, max( 1, (int) $r['limit'] ) );
		// Haversine in SQL; the bbox above keeps the scan small.
		$dist = "(6371 * ACOS(LEAST(1, COS(RADIANS(%f)) * COS(RADIANS(lat)) * COS(RADIANS(lng) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS(lat)))))";
		$sql  = "SELECT post_id, title, lat, lng, acres, city, state, activity_bits, has_photo, $dist AS km FROM " . table( 'park_index' ) . " WHERE " . implode( ' AND ', $w ) . " HAVING km <= %f ORDER BY km ASC LIMIT $limit";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( [ $lat, $lng, $lat ], $a, [ $km ] ) ), ARRAY_A );
		return new \WP_REST_Response( array_map( fn( $p ) => [ 'id' => (int) $p['post_id'], 't' => $p['title'], 'lat' => (float) $p['lat'], 'lng' => (float) $p['lng'], 'ac' => null === $p['acres'] ? null : (float) $p['acres'], 'city' => $p['city'], 'st' => $p['state'], 'bits' => (int) $p['activity_bits'], 'ph' => (int) $p['has_photo'], 'km' => round( (float) $p['km'], 2 ), 'url' => get_permalink( (int) $p['post_id'] ) ], $rows ) );
	}
}
