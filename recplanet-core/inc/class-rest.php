<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * /wp-json/recplanet/v1/counter        the counter, ledger and growth series
 * /wp-json/recplanet/v1/places         parks in a bounding box, filtered, for the atlas
 * /wp-json/recplanet/v1/near           parks by distance from a point
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
			'activity' => [ 'default' => '' ],
			'steward'  => [ 'default' => '' ],
			'min_acres'=> [ 'default' => 0 ],
			'year'     => [ 'default' => 0 ],
			'no_photo' => [ 'default' => 0 ],
			'limit'    => [ 'default' => 2000 ],
		] ] );
		register_rest_route( 'recplanet/v1', '/near', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'near' ], 'permission_callback' => '__return_true', 'args' => [
			'lat' => [ 'required' => true ], 'lng' => [ 'required' => true ], 'km' => [ 'default' => 2 ], 'activity' => [ 'default' => '' ], 'limit' => [ 'default' => 20 ],
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
		$act = $r['activity'];
		if ( '' !== $act ) {
			$i = array_search( $act, ACTIVITIES, true );
			if ( false !== $i ) {
				$w[] = '(activity_bits & %d) <> 0';
				$a[] = 1 << $i;
			}
		}
		if ( '' !== $r['steward'] ) {
			$levels = array_intersect( explode( ',', $r['steward'] ), array_keys( STEWARD_LEVELS ) );
			if ( $levels ) {
				$w[] = 'steward_level IN (' . implode( ',', array_fill( 0, count( $levels ), '%s' ) ) . ')';
				$a   = array_merge( $a, $levels );
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
