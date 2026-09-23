<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps rp_park_index in step with the park posts. One row per published park.
 */
class Index {

	public static function init(): void {
		add_action( 'save_post_' . POST_PARK, [ __CLASS__, 'on_save' ], 20, 2 );
		add_action( 'before_delete_post', [ __CLASS__, 'on_delete' ] );
		add_action( 'transition_post_status', [ __CLASS__, 'on_transition' ], 10, 3 );
	}

	public static function on_save( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' === $post->post_status ) {
			self::upsert( $post_id );
		} else {
			self::remove( $post_id );
		}
	}

	public static function on_transition( string $new, string $old, \WP_Post $post ): void {
		if ( POST_PARK !== $post->post_type || $new === $old ) {
			return;
		}
		if ( 'publish' === $new ) {
			self::upsert( $post->ID );
		} elseif ( 'publish' === $old ) {
			self::remove( $post->ID );
		}
	}

	public static function on_delete( int $post_id ): void {
		if ( get_post_type( $post_id ) === POST_PARK ) {
			self::remove( $post_id );
		}
	}

	/** Build the index row from the post and its meta/terms. Returns the previous acreage (or null). */
	public static function upsert( int $post_id ): ?float {
		global $wpdb;
		$prev = $wpdb->get_var( $wpdb->prepare( "SELECT acres FROM " . table( 'park_index' ) . " WHERE post_id = %d", $post_id ) );
		$prev = null === $prev ? null : (float) $prev;

		$lat = get_post_meta( $post_id, 'rp_lat', true );
		$lng = get_post_meta( $post_id, 'rp_lng', true );
		$acres = get_post_meta( $post_id, 'rp_acreage', true );

		$bits = 0;
		foreach ( wp_get_object_terms( $post_id, TAX_ACTIVITY, [ 'fields' => 'names' ] ) as $name ) {
			$i = array_search( $name, ACTIVITIES, true );
			if ( false !== $i ) {
				$bits |= ( 1 << $i );
			}
		}
		$steward_term  = 0;
		$steward_level = 'other';
		foreach ( wp_get_object_terms( $post_id, TAX_STEWARD ) as $t ) {
			if ( $t->parent ) {
				$steward_term  = $t->term_id;
				$steward_level = get_term( $t->parent )->slug;
			}
		}

		$row = [
			'post_id'        => $post_id,
			'lat'            => '' === $lat ? null : (float) $lat,
			'lng'            => '' === $lng ? null : (float) $lng,
			'acres'          => '' === $acres ? null : (float) $acres,
			'country'        => strtolower( (string) get_post_meta( $post_id, 'rp_country', true ) ),
			'state'          => strtoupper( (string) get_post_meta( $post_id, 'rp_state', true ) ),
			'county'         => (string) get_post_meta( $post_id, 'rp_county', true ),
			'city'           => (string) get_post_meta( $post_id, 'rp_city', true ),
			'steward_term'   => $steward_term,
			'steward_level'  => $steward_level,
			'activity_bits'  => $bits,
			'has_photo'      => has_post_thumbnail( $post_id ) ? 1 : 0,
			'published_year' => (int) get_the_date( 'Y', $post_id ),
			'title'          => get_the_title( $post_id ),
		];
		$wpdb->replace( table( 'park_index' ), $row );
		if ( null !== $row['lat'] && null !== $row['lng'] ) {
			$wpdb->query( $wpdb->prepare( "UPDATE " . table( 'park_index' ) . " SET pt = ST_GeomFromText(%s) WHERE post_id = %d", sprintf( 'POINT(%F %F)', $row['lng'], $row['lat'] ), $post_id ) );
		}
		Counter::park_changed( $post_id, $prev, $row['acres'], $row );
		return $prev;
	}

	public static function remove( int $post_id ): void {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . table( 'park_index' ) . " WHERE post_id = %d", $post_id ), ARRAY_A );
		if ( ! $row ) {
			return;
		}
		$wpdb->delete( table( 'park_index' ), [ 'post_id' => $post_id ] );
		Counter::park_changed( $post_id, (float) $row['acres'], null, $row );
	}

	/** Rebuild every row from scratch. Used by the nightly job and the importer. */
	/** Drops what a long loop has accumulated in the runtime object cache and the query log. */
	public static function free_memory(): void {
		global $wpdb, $wp_object_cache;
		$wpdb->queries = [];
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
		if ( is_object( $wp_object_cache ) ) {
			foreach ( [ 'cache', 'group_ops', 'stats', 'memcache_debug' ] as $prop ) {
				if ( property_exists( $wp_object_cache, $prop ) && is_array( $wp_object_cache->$prop ) ) {
					$wp_object_cache->$prop = [];
				}
			}
		}
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}

	public static function rebuild_all( ?callable $progress = null ): int {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", POST_PARK ) );
		$wpdb->query( "TRUNCATE TABLE " . table( 'park_index' ) );
		Counter::suspend( true );
		wp_suspend_cache_addition( true );          // 67,000 parks would otherwise fill the object cache and exhaust memory
		$n = 0;
		foreach ( $ids as $id ) {
			self::upsert( (int) $id );
			if ( ++$n % 500 === 0 ) {
				self::free_memory();
				if ( $progress ) {
					$progress( $n, count( $ids ) );
				}
			}
		}
		wp_suspend_cache_addition( false );
		Counter::suspend( false );
		Counter::rebuild_rollups();
		return count( $ids );
	}
}
