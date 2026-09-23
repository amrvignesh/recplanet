<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * URLs.
 *   /{state}/{city}/{slug}     a US park, unchanged from the old site
 *   /{state}/{city}            city page (place term)
 *   /{state}/county/{county}   county page
 *   /{state}                   state page
 *   /world/{country}           country page
 *   /world/{country}/{slug}    a non-US park
 * Anything that 404s is looked up in rp_redirects and sent on with a 301.
 */
class Rewrites {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'rules' ] );
		add_filter( 'query_vars', fn( $v ) => array_merge( $v, [ 'rp_state', 'rp_city', 'rp_county', 'rp_country', 'rp_place_level' ] ) );
		add_filter( 'post_type_link', [ __CLASS__, 'park_link' ], 10, 2 );
		add_filter( 'term_link', [ __CLASS__, 'place_link' ], 10, 3 );
		add_action( 'pre_get_posts', [ __CLASS__, 'resolve' ] );
		add_action( 'template_redirect', [ __CLASS__, 'legacy_redirect' ], 1 );
	}

	public static function rules(): void {
		$st = '([a-z]{2})';
		add_rewrite_rule( "^world/([a-z]{2})/([^/]+)/?$", 'index.php?post_type=' . POST_PARK . '&rp_country=$matches[1]&name=$matches[2]', 'top' );
		add_rewrite_rule( "^world/([a-z]{2})/?$", 'index.php?rp_country=$matches[1]&rp_place_level=country', 'top' );
		add_rewrite_rule( "^$st/county/([^/]+)/?$", 'index.php?rp_state=$matches[1]&rp_county=$matches[2]&rp_place_level=county', 'top' );
		add_rewrite_rule( "^$st/([^/]+)/([^/]+)/?$", 'index.php?post_type=' . POST_PARK . '&rp_state=$matches[1]&rp_city=$matches[2]&name=$matches[3]', 'top' );
		add_rewrite_rule( "^$st/([^/]+)/?$", 'index.php?rp_state=$matches[1]&rp_city=$matches[2]&rp_place_level=city', 'top' );
		add_rewrite_rule( "^$st/?$", 'index.php?rp_state=$matches[1]&rp_place_level=state', 'top' );
	}

	/** Build a park's URL from its place meta. */
	public static function park_link( string $link, \WP_Post $post ): string {
		if ( POST_PARK !== $post->post_type ) {
			return $link;
		}
		$country = strtolower( (string) get_post_meta( $post->ID, 'rp_country', true ) ) ?: 'us';
		$state   = strtolower( (string) get_post_meta( $post->ID, 'rp_state', true ) );
		$city    = legacy_slug( (string) get_post_meta( $post->ID, 'rp_city', true ) );
		if ( 'us' === $country && $state && $city ) {
			return home_url( "/$state/$city/{$post->post_name}" );
		}
		if ( 'us' === $country && $state ) {
			return home_url( "/$state/{$post->post_name}" );        // no city on record; handled by the city rule with an empty-city fallback
		}
		return home_url( "/world/$country/{$post->post_name}" );
	}

	/** Place terms get the same short URLs. */
	public static function place_link( string $link, \WP_Term $term, string $taxonomy ): string {
		if ( TAX_PLACE !== $taxonomy ) {
			return $link;
		}
		$chain = [];
		$t     = $term;
		while ( $t && ! is_wp_error( $t ) ) {
			$chain[] = $t;
			$t       = $t->parent ? get_term( $t->parent, TAX_PLACE ) : null;
		}
		$chain   = array_reverse( $chain );                      // country, state, county, city
		$country = $chain[0]->slug;
		$level   = get_term_meta( $term->term_id, 'rp_level', true );
		if ( 'us' !== $country ) {
			return home_url( '/world/' . $country . ( isset( $chain[1] ) ? '/' . $chain[1]->slug : '' ) );
		}
		switch ( $level ) {
			case 'country': return home_url( '/' );
			case 'state':   return home_url( '/' . $chain[1]->slug );
			case 'county':  return home_url( '/' . $chain[1]->slug . '/county/' . $term->slug );
			case 'city':    return home_url( '/' . $chain[1]->slug . '/' . $term->slug );
		}
		return $link;
	}

	/** Turn /{state}/{city} etc. into a place-term archive, and disambiguate park slugs by place. */
	public static function resolve( \WP_Query $q ): void {
		if ( ! $q->is_main_query() || is_admin() ) {
			return;
		}
		$level = $q->get( 'rp_place_level' );
		if ( $level ) {
			$term = self::find_place( $level, strtolower( (string) $q->get( 'rp_country' ) ) ?: 'us', (string) $q->get( 'rp_state' ), (string) $q->get( 'rp_county' ), (string) $q->get( 'rp_city' ) );
			if ( $term ) {
				$q->set( 'rp_place_level', '' );
				$q->set( 'taxonomy', TAX_PLACE );
				$q->set( 'term', $term->slug );
				$q->set( 'rp_place', $term->slug );
				$q->set( 'post_type', POST_PARK );
				$q->set( 'posts_per_page', 50 );
				$q->set( 'orderby', 'meta_value_num' );
				$q->set( 'meta_key', 'rp_acreage' );
				$q->set( 'order', 'DESC' );
				$q->set( 'tax_query', [ [ 'taxonomy' => TAX_PLACE, 'field' => 'term_id', 'terms' => $term->term_id, 'include_children' => true ] ] );
				self::flags( $q, 'tax', $term );
				return;
			}
			// /{state}/{slug}: a park that has no city on record (national forests, wildlife areas).
			if ( 'city' === $level ) {
				$park = get_posts( [ 'post_type' => POST_PARK, 'name' => (string) $q->get( 'rp_city' ), 'post_status' => 'publish', 'posts_per_page' => 1,
					'meta_query' => [ [ 'key' => 'rp_state', 'value' => strtoupper( (string) $q->get( 'rp_state' ) ) ] ] ] );
				if ( $park ) {
					$q->set( 'rp_place_level', '' );
					$q->set( 'post_type', POST_PARK );
					$q->set( 'name', $park[0]->post_name );
					$q->set( 'p', $park[0]->ID );
					$q->set( 'rp_city', '' );
					self::flags( $q, 'single', $park[0] );
					return;
				}
			}
			$q->set_404();
			return;
		}
		// Park with the same slug in two cities: pin it to the place in the URL.
		if ( POST_PARK === $q->get( 'post_type' ) && $q->get( 'name' ) && ( $q->get( 'rp_state' ) || $q->get( 'rp_country' ) ) ) {
			$meta = [ 'relation' => 'AND' ];
			if ( $q->get( 'rp_state' ) ) {
				$meta[] = [ 'key' => 'rp_state', 'value' => strtoupper( $q->get( 'rp_state' ) ) ];
			}
			if ( $q->get( 'rp_country' ) ) {
				$meta[] = [ 'key' => 'rp_country', 'value' => strtolower( $q->get( 'rp_country' ) ) ];
			}
			$q->set( 'meta_query', $meta );
		}
	}

	/** WordPress decided is_home before we changed the query; set the flags it will read for the template. */
	private static function flags( \WP_Query $q, string $kind, $obj ): void {
		$q->is_home = false; $q->is_front_page = false; $q->is_404 = false; $q->is_page = false;
		if ( 'tax' === $kind ) {
			$q->is_tax = true; $q->is_archive = true; $q->is_single = false; $q->is_singular = false;
		} else {
			$q->is_tax = false; $q->is_archive = false; $q->is_single = true; $q->is_singular = true;
		}
		$q->queried_object = $obj; $q->queried_object_id = 'tax' === $kind ? $obj->term_id : $obj->ID;
	}

	private static function find_place( string $level, string $country, string $state, string $county, string $city ): ?\WP_Term {
		$parent = self::term_by_slug( $country, 0 );
		if ( ! $parent ) {
			return null;
		}
		if ( 'country' === $level ) {
			return $parent;
		}
		$st = self::term_by_slug( strtolower( $state ), $parent->term_id );
		if ( ! $st ) {
			return null;
		}
		if ( 'state' === $level ) {
			return $st;
		}
		if ( 'county' === $level ) {
			return self::term_by_slug( $county, $st->term_id );
		}
		// city: may sit under the state or under a county
		$c = self::term_by_slug( $city, $st->term_id );
		if ( $c ) {
			return $c;
		}
		$all = get_terms( [ 'taxonomy' => TAX_PLACE, 'slug' => $city, 'hide_empty' => false ] );
		foreach ( $all as $t ) {
			$p = get_term( $t->parent, TAX_PLACE );
			if ( $p && $p->parent === $st->term_id ) {
				return $t;
			}
		}
		return null;
	}

	private static function term_by_slug( string $slug, int $parent ): ?\WP_Term {
		if ( '' === $slug ) {
			return null;
		}
		$t = get_terms( [ 'taxonomy' => TAX_PLACE, 'slug' => $slug, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ] );
		return ( $t && ! is_wp_error( $t ) ) ? $t[0] : null;
	}

	/** 404 → look the old path up → 301. One indexed query, only on misses. */
	public static function legacy_redirect(): void {
		if ( ! is_404() ) {
			return;
		}
		global $wpdb;
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return;
		}
		$path = rawurldecode( $path );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT new_path FROM " . table( 'redirects' ) . " WHERE old_path = %s", $path ) );
		if ( ! $row ) {
			// The old site served park aliases with and without the state segment; try the last segment as a park slug.
			$slug = basename( $path );
			$p    = get_posts( [ 'post_type' => POST_PARK, 'name' => $slug, 'posts_per_page' => 1, 'post_status' => 'publish' ] );
			if ( $p ) {
				wp_redirect( get_permalink( $p[0] ), 301 );
				exit;
			}
			return;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE " . table( 'redirects' ) . " SET hits = hits + 1 WHERE old_path = %s", $path ) );
		wp_redirect( home_url( '/' . ltrim( $row->new_path, '/' ) ), 301 );
		exit;
	}

	/** Used by the importer. */
	public static function add_redirect( string $old, string $new, string $kind ): void {
		global $wpdb;
		$old = trim( $old, '/' );
		$new = trim( $new, '/' );
		if ( '' === $old || $old === $new ) {
			return;
		}
		$wpdb->replace( table( 'redirects' ), [ 'old_path' => mb_substr( $old, 0, 191 ), 'new_path' => mb_substr( $new, 0, 191 ), 'kind' => $kind ] );
	}
}
