<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * What WordPress's own sitemaps leave out, added to /wp-sitemap.xml:
 *   wp-sitemap-lists-1.xml     the photo and contest lists (post type archives, which core never lists)
 *   wp-sitemap-filters-N.xml   a place narrowed to an activity or facility (/tx/houston/playground/), once it holds
 *                              at least MIN places; thinner ones repeat their parks' own pages
 * Steward pages (/managed-by/…) are core's taxonomy sitemap, switched on in Rewrites::init().
 * The filter list is built from the index once a day and kept in the object cache a sitemap page at a time.
 */
class Sitemaps {

	const MIN      = 3;
	const PER_PAGE = 2000;
	const TTL      = DAY_IN_SECONDS;

	public static function init(): void {
		add_action( 'wp_sitemaps_init', [ __CLASS__, 'register' ] );
	}

	public static function register( \WP_Sitemaps $server ): void {
		$server->registry->add_provider( 'lists', new class() extends \WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'lists';
				$this->object_type = 'lists';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				if ( 1 !== (int) $page_num ) {
					return [];
				}
				return array_map( fn( $u ) => [ 'loc' => $u ], array_values( array_filter( [ get_post_type_archive_link( POST_PHOTO ), get_post_type_archive_link( POST_CONTEST ) ] ) ) );
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return 1;
			}
		} );
		$server->registry->add_provider( 'filters', new class() extends \WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'filters';
				$this->object_type = 'filters';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				return array_map( fn( $u ) => [ 'loc' => $u ], Sitemaps::page( (int) $page_num ) );
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return Sitemaps::pages();
			}
		} );
	}

	/** How many filter sitemap files there are. */
	public static function pages(): int {
		$n = get_transient( 'rp_sm_filters_pages' );
		if ( false === $n ) {
			$n = self::build();
		}
		return (int) $n;
	}

	/** One filter sitemap file's URLs. */
	public static function page( int $page ): array {
		$urls = get_transient( 'rp_sm_filters_' . $page );
		if ( false === $urls ) {
			self::build();
			$urls = get_transient( 'rp_sm_filters_' . $page );
		}
		return is_array( $urls ) ? $urls : [];
	}

	/** Build every narrowed-place URL with MIN or more places, store it in pages, return the page count. */
	private static function build(): int {
		global $wpdb;
		$idx   = table( 'park_index' );
		$links = self::place_links();
		$urls  = [];

		// Activities: one pass over the index, counted per country, state and city.
		$slugs = [];
		foreach ( ACTIVITIES as $i => $name ) {
			$t = get_term_by( 'name', $name, TAX_ACTIVITY );
			if ( $t instanceof \WP_Term ) {
				$slugs[ $i ] = $t->slug;
			}
		}
		$count = [];
		foreach ( $wpdb->get_results( "SELECT country, state, city, activity_bits FROM $idx WHERE activity_bits <> 0", ARRAY_N ) as [ $c, $s, $city, $bits ] ) {
			$keys = [ $c, "$c|$s" ];
			if ( '' !== $city ) {
				$keys[] = "$c|$s|$city";
			}
			foreach ( $slugs as $i => $slug ) {
				if ( (int) $bits & ( 1 << $i ) ) {
					foreach ( $keys as $k ) {
						$count[ $k ][ $slug ] = ( $count[ $k ][ $slug ] ?? 0 ) + 1;
					}
				}
			}
		}
		foreach ( $count as $k => $by ) {
			if ( isset( $links[ $k ] ) ) {
				foreach ( $by as $slug => $n ) {
					if ( $n >= self::MIN ) {
						$urls[] = $links[ $k ] . $slug . '/';
					}
				}
			}
		}

		// Facilities, except those that share a slug with an activity (that address shows the activity).
		$taken = array_flip( $slugs );
		foreach ( [ "country, state, city", "country, state, ''", "country, '', ''" ] as $cols ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT CONCAT_WS('|', $cols) k, t.slug, COUNT(*) n FROM $idx i
				 JOIN {$wpdb->term_relationships} tr ON tr.object_id = i.post_id
				 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 GROUP BY $cols, t.slug HAVING n >= %d", TAX_FACILITY, self::MIN ) );
			foreach ( $rows as $r ) {
				$k = rtrim( $r->k, '|' );
				if ( isset( $links[ $k ] ) && ! isset( $taken[ $r->slug ] ) ) {
					$urls[] = $links[ $k ] . $r->slug . '/';
				}
			}
		}

		$urls   = array_values( array_unique( $urls ) );
		sort( $urls );
		$chunks = array_chunk( $urls, self::PER_PAGE );
		foreach ( $chunks as $i => $chunk ) {
			set_transient( 'rp_sm_filters_' . ( $i + 1 ), $chunk, self::TTL );
		}
		set_transient( 'rp_sm_filters_pages', count( $chunks ), self::TTL );
		return count( $chunks );
	}

	/**
	 * Place page URLs (with a trailing slash) keyed the way the index names a place: "ca" for a country, "us|TX" for a
	 * state, "us|TX|Houston" for a city. The United States itself has no list page of its own, so it is left out.
	 */
	private static function place_links(): array {
		$out = [];
		// hide_empty would drop states and countries: their parks sit on the city terms below them.
		foreach ( get_terms( [ 'taxonomy' => TAX_PLACE, 'hide_empty' => false ] ) as $t ) {
			$level = get_term_meta( $t->term_id, 'rp_level', true );
			if ( ! in_array( $level, [ 'country', 'state', 'city' ], true ) ) {
				continue;
			}
			$chain = [];
			for ( $cur = $t; $cur instanceof \WP_Term; $cur = $cur->parent ? get_term( $cur->parent, TAX_PLACE ) : null ) {
				$chain[ get_term_meta( $cur->term_id, 'rp_level', true ) ] = $cur;
			}
			if ( ! isset( $chain['country'] ) ) {
				continue;
			}
			$c = strtolower( Rewrites::url_slug( $chain['country'] ) );
			$s = isset( $chain['state'] ) ? strtoupper( Rewrites::url_slug( $chain['state'] ) ) : '';
			$k = 'country' === $level ? $c : ( 'state' === $level ? "$c|$s" : "$c|$s|{$t->name}" );
			if ( '' === $c || 'us' === $k || isset( $out[ $k ] ) ) {
				continue;
			}
			$link = get_term_link( $t );
			if ( is_string( $link ) ) {
				$out[ $k ] = trailingslashit( $link );
			}
		}
		return $out;
	}
}
