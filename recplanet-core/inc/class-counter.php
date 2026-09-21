<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * The acre counter. Always a SUM over published parks, never a stored constant.
 *
 * rp_acre_rollup holds the sums per scope (world, country, state, county, city, steward) and is
 * recomputed for the affected scopes whenever a park changes. rp_acre_ledger is the public feed.
 * A nightly job re-sums everything and logs any drift.
 */
class Counter {

	private static bool $suspended = false;

	public static function init(): void {
		add_action( 'rp_nightly_rebuild', [ __CLASS__, 'nightly' ] );
	}

	public static function suspend( bool $on ): void {
		self::$suspended = $on;
	}

	/** Called by Index after a row is written or removed. $row is the index row (new, or old when removing). */
	public static function park_changed( int $post_id, ?float $old_acres, ?float $new_acres, array $row ): void {
		if ( self::$suspended ) {
			return;
		}
		self::recompute_scopes( $row );
		$delta = (float) ( $new_acres ?? 0 ) - (float) ( $old_acres ?? 0 );
		if ( null === $old_acres && null !== $new_acres ) {
			$event = 'added';
		} elseif ( null !== $old_acres && null === $new_acres ) {
			$event = 'removed';
		} elseif ( abs( $delta ) >= 0.005 ) {
			$event = 'corrected';
		} else {
			return;
		}
		global $wpdb;
		$wpdb->insert( table( 'acre_ledger' ), [
			'post_id'     => $post_id,
			'title'       => $row['title'] ?? get_the_title( $post_id ),
			'place'       => trim( ( $row['city'] ?? '' ) . ' ' . ( $row['state'] ?? '' ) ),
			'event'       => $event,
			'delta_acres' => $delta,
			'created_at'  => current_time( 'mysql', true ),
		] );
		wp_cache_delete( 'rp_counter_payload', 'recplanet' );
	}

	/** Recompute the roll-ups this row belongs to: world, its country, state, county, city, steward. */
	public static function recompute_scopes( array $row ): void {
		$scopes = [ [ 'world', 'world' ] ];
		if ( $row['country'] ) {
			$scopes[] = [ 'country', $row['country'] ];
		}
		if ( $row['country'] && $row['state'] ) {
			$scopes[] = [ 'state', $row['country'] . '/' . $row['state'] ];
			if ( $row['county'] ) {
				$scopes[] = [ 'county', $row['country'] . '/' . $row['state'] . '/' . legacy_slug( $row['county'] ) ];
			}
			if ( $row['city'] ) {
				$scopes[] = [ 'city', $row['country'] . '/' . $row['state'] . '/' . legacy_slug( $row['city'] ) ];
			}
		}
		if ( ! empty( $row['steward_term'] ) ) {
			$scopes[] = [ 'steward', (string) $row['steward_term'] ];
		}
		foreach ( $scopes as [ $scope, $key ] ) {
			self::recompute( $scope, $key );
		}
	}

	private static function where( string $scope, string $key ): array {
		global $wpdb;
		$p = explode( '/', $key );
		switch ( $scope ) {
			case 'world':   return [ '1=1', [] ];
			case 'country': return [ 'country = %s', [ $p[0] ] ];
			case 'state':   return [ 'country = %s AND state = %s', [ $p[0], $p[1] ] ];
			case 'county':  return [ 'country = %s AND state = %s AND county = %s', [ $p[0], $p[1], self::unslug_county( $p[0], $p[1], $p[2] ) ] ];
			case 'city':    return [ 'country = %s AND state = %s AND city = %s', [ $p[0], $p[1], self::unslug_city( $p[0], $p[1], $p[2] ) ] ];
			case 'steward': return [ 'steward_term = %d', [ (int) $key ] ];
		}
		return [ '1=0', [] ];
	}

	/** City names are stored as text in the index; find the stored spelling for a slug. */
	private static function unslug_city( string $c, string $s, string $slug ): string {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT city FROM " . table( 'park_index' ) . " WHERE country = %s AND state = %s", $c, $s ) );
		foreach ( $names as $n ) {
			if ( legacy_slug( $n ) === $slug ) {
				return $n;
			}
		}
		return $slug;
	}
	private static function unslug_county( string $c, string $s, string $slug ): string {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT county FROM " . table( 'park_index' ) . " WHERE country = %s AND state = %s", $c, $s ) );
		foreach ( $names as $n ) {
			if ( legacy_slug( $n ) === $slug ) {
				return $n;
			}
		}
		return $slug;
	}

	public static function recompute( string $scope, string $key ): void {
		global $wpdb;
		[ $where, $args ] = self::where( $scope, $key );
		$sql = "SELECT COUNT(*) AS n, COALESCE(SUM(acres),0) AS a FROM " . table( 'park_index' ) . " WHERE $where";
		$r   = $wpdb->get_row( $args ? $wpdb->prepare( $sql, $args ) : $sql );
		$wpdb->replace( table( 'acre_rollup' ), [
			'scope' => $scope, 'scope_key' => $key, 'park_count' => (int) $r->n, 'acres' => (float) $r->a, 'updated_at' => current_time( 'mysql', true ),
		] );
	}

	/** Full rebuild of every roll-up from the index table. */
	public static function rebuild_rollups(): void {
		global $wpdb;
		$t = table( 'park_index' );
		$wpdb->query( "TRUNCATE TABLE " . table( 'acre_rollup' ) );
		$now = current_time( 'mysql', true );
		$ins = "INSERT INTO " . table( 'acre_rollup' ) . " (scope, scope_key, park_count, acres, updated_at) ";
		$wpdb->query( $wpdb->prepare( $ins . "SELECT 'world','world',COUNT(*),COALESCE(SUM(acres),0),%s FROM $t", $now ) );
		$wpdb->query( $wpdb->prepare( $ins . "SELECT 'country',country,COUNT(*),COALESCE(SUM(acres),0),%s FROM $t WHERE country<>'' GROUP BY country", $now ) );
		$wpdb->query( $wpdb->prepare( $ins . "SELECT 'state',CONCAT(country,'/',state),COUNT(*),COALESCE(SUM(acres),0),%s FROM $t WHERE country<>'' AND state<>'' GROUP BY country,state", $now ) );
		$wpdb->query( $wpdb->prepare( $ins . "SELECT 'steward',steward_term,COUNT(*),COALESCE(SUM(acres),0),%s FROM $t WHERE steward_term>0 GROUP BY steward_term", $now ) );
		// county and city keys use slugs, which MySQL can't compute; do them in PHP.
		foreach ( [ 'county', 'city' ] as $level ) {
			$rows = $wpdb->get_results( "SELECT country,state,$level AS name,COUNT(*) AS n,COALESCE(SUM(acres),0) AS a FROM $t WHERE country<>'' AND state<>'' AND $level<>'' GROUP BY country,state,$level" );
			foreach ( $rows as $r ) {
				$wpdb->replace( table( 'acre_rollup' ), [ 'scope' => $level, 'scope_key' => $r->country . '/' . $r->state . '/' . legacy_slug( $r->name ), 'park_count' => (int) $r->n, 'acres' => (float) $r->a, 'updated_at' => $now ] );
			}
		}
		wp_cache_delete( 'rp_counter_payload', 'recplanet' );
	}

	/** Read one roll-up. */
	public static function get( string $scope, string $key ): array {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT park_count, acres FROM " . table( 'acre_rollup' ) . " WHERE scope = %s AND scope_key = %s", $scope, $key ) );
		return [ 'count' => (int) ( $r->park_count ?? 0 ), 'acres' => (float) ( $r->acres ?? 0 ) ];
	}

	/** The payload the home page, the widget and the REST endpoint all use. */
	public static function payload(): array {
		$cached = wp_cache_get( 'rp_counter_payload', 'recplanet' );
		if ( $cached ) {
			return $cached;
		}
		global $wpdb;
		$world = self::get( 'world', 'world' );
		$us    = self::get( 'country', 'us' );
		$aq    = self::get( 'country', 'aq' );
		$ledger = $wpdb->get_results( "SELECT title, place, event, delta_acres, created_at FROM " . table( 'acre_ledger' ) . " ORDER BY id DESC LIMIT 20", ARRAY_A );
		$out = [
			'world'      => $world,
			'us'         => $us,
			'antarctica' => $aq,
			'rest'       => [ 'count' => $world['count'] - $us['count'] - $aq['count'], 'acres' => $world['acres'] - $us['acres'] - $aq['acres'] ],
			'headline'   => get_option( 'rp_counter_headline', 'world' ),
			'ledger'     => $ledger,
			'updated_at' => current_time( 'c' ),
		];
		wp_cache_set( 'rp_counter_payload', $out, 'recplanet', 300 );
		return $out;
	}

	/** Places on file per year, for the growth chart. */
	public static function growth(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT published_year AS y, COUNT(*) AS n, COALESCE(SUM(acres),0) AS a FROM " . table( 'park_index' ) . " GROUP BY published_year ORDER BY published_year", ARRAY_A );
	}

	/** Nightly: re-sum everything and record any drift from the incremental numbers. */
	public static function nightly(): void {
		$before = self::get( 'world', 'world' );
		Index::rebuild_all();
		$after = self::get( 'world', 'world' );
		if ( abs( $before['acres'] - $after['acres'] ) >= 0.01 || $before['count'] !== $after['count'] ) {
			update_option( 'rp_counter_drift', [ 'when' => current_time( 'mysql', true ), 'before' => $before, 'after' => $after ], false );
		}
	}
}
