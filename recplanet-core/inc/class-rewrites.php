<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * URLs.
 *   /{state}/{city}/{slug}     a US park, unchanged from the old site
 *   /{state}/{city}            city page (place term)
 *   /{state}/{city}/{facility} city page narrowed to a facility or activity (picnic-areas, fishing); also /{state}/{facility}
 *   /{state}/county/{county}   county page
 *   /{state}                   state page
 *   /world/{country}           country page
 *   /world/{country}/{slug}    a non-US park
 * Anything that 404s is looked up in rp_redirects and sent on with a 301.
 */
class Rewrites {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'rules' ] );
		add_filter( 'query_vars', fn( $v ) => array_merge( $v, [ 'rp_state', 'rp_city', 'rp_county', 'rp_country', 'rp_place_level', 'rp_filter' ] ) );
		add_filter( 'post_type_link', [ __CLASS__, 'park_link' ], 10, 2 );
		add_filter( 'term_link', [ __CLASS__, 'place_link' ], 10, 3 );
		add_action( 'pre_get_posts', [ __CLASS__, 'resolve' ] );
		add_action( 'template_redirect', [ __CLASS__, 'legacy_redirect' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'random_park' ], 0 );
	}

	public static function rules(): void {
		$st = '([a-z]{2})';
		// Pagination first, so "page" is never read as a city.
		add_rewrite_rule( "^world/([a-z]{2})/page/([0-9]+)/?$", 'index.php?rp_country=$matches[1]&rp_place_level=country&paged=$matches[2]', 'top' );
		add_rewrite_rule( "^$st/([^/]+)/([^/]+)/page/([0-9]+)/?$", 'index.php?rp_state=$matches[1]&rp_city=$matches[2]&rp_filter=$matches[3]&rp_place_level=city&paged=$matches[4]', 'top' );
		add_rewrite_rule( "^$st/county/([^/]+)/page/([0-9]+)/?$", 'index.php?rp_state=$matches[1]&rp_county=$matches[2]&rp_place_level=county&paged=$matches[3]', 'top' );
		add_rewrite_rule( "^$st/([^/]+)/page/([0-9]+)/?$", 'index.php?rp_state=$matches[1]&rp_city=$matches[2]&rp_place_level=city&paged=$matches[3]', 'top' );
		add_rewrite_rule( "^$st/page/([0-9]+)/?$", 'index.php?rp_state=$matches[1]&rp_place_level=state&paged=$matches[2]', 'top' );
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
		// The old site's own path, kept exactly, when it has the shape the router expects. Slugs that WordPress had
		// to make unique (hillside-park-10) never reach the address bar; the old path is the canonical URL.
		$legacy = (string) get_post_meta( $post->ID, 'rp_legacy_path', true );
		if ( 'us' === $country && $state && '' !== $legacy && preg_match( '#^' . preg_quote( $state, '#' ) . '/(?:' . preg_quote( $city, '#' ) . '/)?[^/]+$#', $legacy ) && ! preg_match( '#/(page|county)/#', '/' . $legacy . '/' ) ) {
			return user_trailingslashit( home_url( '/' . $legacy ) );
		}
		if ( 'us' === $country && $state && $city ) {
			return user_trailingslashit( home_url( "/$state/$city/{$post->post_name}" ) );
		}
		if ( 'us' === $country && $state ) {
			return user_trailingslashit( home_url( "/$state/{$post->post_name}" ) );        // no city on record; handled by the city rule with an empty-city fallback
		}
		return user_trailingslashit( home_url( "/world/$country/{$post->post_name}" ) );
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
			return user_trailingslashit( home_url( '/world/' . $country . ( isset( $chain[1] ) ? '/' . $chain[1]->slug : '' ) ) );
		}
		switch ( $level ) {
			case 'country': return home_url( '/' );
			case 'state':   return user_trailingslashit( home_url( '/' . $chain[1]->slug ) );
			case 'county':  return user_trailingslashit( home_url( '/' . $chain[1]->slug . '/county/' . self::url_slug( $term ) ) );
			case 'city':    return user_trailingslashit( home_url( '/' . $chain[1]->slug . '/' . self::url_slug( $term ) ) );
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
			$country = strtolower( (string) $q->get( 'rp_country' ) ) ?: 'us';
			$filter  = self::filter_term( (string) $q->get( 'rp_filter' ) );
			if ( $q->get( 'rp_filter' ) && ! $filter ) {
				self::not_found( $q );
				return;
			}
			$term = self::find_place( $level, $country, (string) $q->get( 'rp_state' ), (string) $q->get( 'rp_county' ), (string) $q->get( 'rp_city' ) );
			// /{state}/{facility-or-activity}: the state page, narrowed.
			if ( ! $term && 'city' === $level && ! $filter ) {
				$f = self::filter_term( (string) $q->get( 'rp_city' ) );
				if ( $f ) {
					$term   = self::find_place( 'state', $country, (string) $q->get( 'rp_state' ), '', '' );
					$filter = $f;
				}
			}
			if ( $term ) {
				self::place_archive( $q, $term, $filter );
				return;
			}
			// /{state}/{slug}: a park that has no city on record (national forests, wildlife areas).
			if ( 'city' === $level && ! $filter ) {
				$park = get_posts( [ 'post_type' => POST_PARK, 'name' => (string) $q->get( 'rp_city' ), 'post_status' => 'publish', 'posts_per_page' => 1,
					'meta_query' => [ [ 'key' => 'rp_state', 'value' => strtoupper( (string) $q->get( 'rp_state' ) ) ] ] ] );
				if ( ! $park ) {
					$id   = self::park_by_path( strtolower( (string) $q->get( 'rp_state' ) ) . '/' . $q->get( 'rp_city' ) );
					$park = $id ? [ get_post( $id ) ] : [];
				}
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
			self::not_found( $q );
			return;
		}
		// /{state}/{city}/{slug}: a park; or, when no park has that slug, the city narrowed to a facility or activity.
		if ( POST_PARK === $q->get( 'post_type' ) && $q->get( 'name' ) && ( $q->get( 'rp_state' ) || $q->get( 'rp_country' ) ) ) {
			if ( $q->get( 'rp_state' ) && $q->get( 'rp_city' ) && ! self::park_exists( (string) $q->get( 'name' ), (string) $q->get( 'rp_state' ) ) ) {
				// The old site's path for a park whose slug WordPress had to make unique.
				$id = self::park_by_path( strtolower( (string) $q->get( 'rp_state' ) ) . '/' . $q->get( 'rp_city' ) . '/' . $q->get( 'name' ) );
				if ( $id ) {
					$q->set( 'name', '' );
					$q->set( 'p', $id );
					return;
				}
				$f = self::filter_term( (string) $q->get( 'name' ) );
				if ( $f ) {
					$term = self::find_place( 'city', 'us', (string) $q->get( 'rp_state' ), '', (string) $q->get( 'rp_city' ) );
					if ( $term ) {
						$q->set( 'name', '' );
						self::place_archive( $q, $term, $f );
						return;
					}
				}
			}
			// Park with the same slug in two cities: pin it to the place in the URL.
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

	/** Turn the main query into a place listing, optionally narrowed to one facility or activity term. */
	private static function place_archive( \WP_Query $q, \WP_Term $term, ?\WP_Term $filter ): void {
		$q->set( 'rp_place_level', '' );
		$q->set( 'taxonomy', TAX_PLACE );
		$q->set( 'term', $term->slug );
		$q->set( 'rp_place', $term->slug );
		$q->set( 'post_type', POST_PARK );
		$q->set( 'posts_per_page', 50 );
		$q->set( 'orderby', 'meta_value_num' );
		$q->set( 'meta_key', 'rp_acreage' );
		$q->set( 'order', 'DESC' );
		$tax = [ [ 'taxonomy' => TAX_PLACE, 'field' => 'term_id', 'terms' => $term->term_id, 'include_children' => true ] ];
		if ( $filter ) {
			$tax['relation'] = 'AND';
			$tax[]           = [ 'taxonomy' => $filter->taxonomy, 'field' => 'term_id', 'terms' => $filter->term_id ];
		}
		$q->set( 'rp_filter', $filter ? $filter->slug : '' );
		$q->set( 'tax_query', $tax );
		self::flags( $q, 'tax', $term );
	}

	/** A facility or activity term by slug. The two words URLs reserve are never terms. */
	public static function filter_term( string $slug ): ?\WP_Term {
		if ( '' === $slug || in_array( $slug, [ 'page', 'county' ], true ) ) {
			return null;
		}
		foreach ( [ TAX_FACILITY, TAX_ACTIVITY ] as $tax ) {
			$t = get_term_by( 'slug', $slug, $tax );
			if ( $t instanceof \WP_Term ) {
				return $t;
			}
		}
		return null;
	}

	/** A published park by the path it had on the old site. */
	public static function park_by_path( string $path ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rp_legacy_path' WHERE m.meta_value = %s AND p.post_type = %s AND p.post_status = 'publish' LIMIT 1", trim( $path, '/' ), POST_PARK ) );
	}

	private static function park_exists( string $slug, string $state ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rp_state' WHERE p.post_name = %s AND p.post_type = %s AND p.post_status = 'publish' AND m.meta_value = %s LIMIT 1", $slug, POST_PARK, strtoupper( $state ) ) );
	}

	/** A real 404: no posts at all, so WordPress sends a 404 status rather than a 200 with the 404 template. */
	private static function not_found( \WP_Query $q ): void {
		$q->set_404();
		$q->set( 'rp_place_level', '' );
		$q->set( 'post_type', POST_PARK );
		$q->set( 'post__in', [ 0 ] );
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

	public static function find_place( string $level, string $country, string $state, string $county, string $city ): ?\WP_Term {
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
			foreach ( self::terms_by_url_slug( $county ) as $t ) {
				if ( $t->parent === $st->term_id ) {
					return $t;
				}
			}
			return null;
		}
		// city: may sit under the state or under a county
		foreach ( self::terms_by_url_slug( $city ) as $t ) {
			if ( $t->parent === $st->term_id ) {
				return $t;
			}
			$p = $t->parent ? get_term( $t->parent, TAX_PLACE ) : null;
			if ( $p instanceof \WP_Term && $p->parent === $st->term_id ) {
				return $t;
			}
		}
		return null;
	}

	/** The segment a place uses in URLs: the slug of its name, kept in meta because term slugs are unique site-wide. */
	public static function url_slug( \WP_Term $term ): string {
		$s = (string) get_term_meta( $term->term_id, 'rp_slug', true );
		return '' !== $s ? $s : legacy_slug( $term->name );
	}

	/** Every place term whose URL segment is this (Springfield exists in many states). */
	private static function terms_by_url_slug( string $slug ): array {
		if ( '' === $slug ) {
			return [];
		}
		$t = get_terms( [ 'taxonomy' => TAX_PLACE, 'hide_empty' => false, 'meta_key' => 'rp_slug', 'meta_value' => $slug ] );
		if ( ! is_array( $t ) || ! $t ) {
			$t = get_terms( [ 'taxonomy' => TAX_PLACE, 'slug' => $slug, 'hide_empty' => false ] );
		}
		return is_array( $t ) ? $t : [];
	}

	private static function term_by_slug( string $slug, int $parent ): ?\WP_Term {
		if ( '' === $slug ) {
			return null;
		}
		$t = get_terms( [ 'taxonomy' => TAX_PLACE, 'slug' => $slug, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ] );
		return ( $t && ! is_wp_error( $t ) ) ? $t[0] : null;
	}

	/** /random/ sends the visitor to a random published park. Spin the globe. */
	public static function random_park(): void {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( 'random' !== $path ) {
			return;
		}
		global $wpdb;
		$n  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . table( 'park_index' ) );
		$id = $n ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM " . table( 'park_index' ) . " LIMIT 1 OFFSET %d", random_int( 0, max( 0, $n - 1 ) ) ) ) : 0;
		nocache_headers();
		wp_redirect( $id ? get_permalink( $id ) : home_url( '/atlas/' ), 302 );
		exit;
	}

	/** 404 → the old top-level pages, then /node/N, then the redirect table, then the shapes that never had aliases → 301. */
	public static function legacy_redirect(): void {
		if ( ! is_404() ) {
			return;
		}
		// Core's handle_404() bails when the query was already flagged 404 (as resolve() does), so the status header
		// would stay 200. Send it here; a redirect below replaces it with a 301.
		status_header( 404 );
		nocache_headers();
		global $wpdb;
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return;
		}
		$path = rawurldecode( $path );
		$path = (string) preg_replace( '#/(feed|track|rss\.xml)$#', '', $path );     // the old site's per-page feeds and "track" tabs
		$to   = self::old_site_url( $path, $_GET );
		if ( '' === $to ) {
			$to = self::old_node_url( $path );
		}
		if ( '' === $to ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT new_path FROM " . table( 'redirects' ) . " WHERE old_path = %s", $path ) );
			if ( $row ) {
				$wpdb->query( $wpdb->prepare( "UPDATE " . table( 'redirects' ) . " SET hits = hits + 1 WHERE old_path = %s", $path ) );
				$to = '/' . ltrim( $row->new_path, '/' );
			}
		}
		if ( '' === $to ) {
			$to = self::old_path_guess( $path );
		}
		if ( '' === $to ) {
			return;
		}
		wp_redirect( home_url( $to ), 301 );
		exit;
	}

	/** /node/N by the Drupal node id kept on every imported park, photo and post. */
	private static function old_node_url( string $path ): string {
		global $wpdb;
		if ( ! preg_match( '#^node/(\d+)#', $path, $m ) ) {
			return '';
		}
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rp_legacy_nid' AND meta_value = %s LIMIT 1", $m[1] ) );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
		}
		return '/';
	}

	/** Old paths that never had aliases: member pages, member blogs, uploaded files, stray tag pages, bare park slugs. */
	private static function old_path_guess( string $path ): string {
		global $wpdb;
		if ( preg_match( '#^users?(/|$)#', $path ) ) {
			return '/contest/';
		}
		if ( preg_match( '#^blogs?(/|$)#', $path ) ) {
			return '/blog/';
		}
		if ( preg_match( '#^sites/default/files/(?:imagecache/[^/]+/)?(.+)$#', $path, $m ) ) {
			$id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s ORDER BY ID LIMIT 1", '%/' . $wpdb->esc_like( basename( $m[1] ) ) ) );
			$url = $id ? wp_get_attachment_url( $id ) : '';
			return $url ? (string) wp_parse_url( $url, PHP_URL_PATH ) : '/';
		}
		if ( preg_match( '#^category/[^/]+/(.+)$#', $path, $m ) ) {
			return '/?s=' . rawurlencode( str_replace( '-', ' ', basename( $m[1] ) ) );
		}
		if ( str_starts_with( $path, 'taxonomy/term/' ) ) {
			return '/states/';
		}
		// The old site served park aliases with and without the state segment; try the last segment as a park slug.
		$p = get_posts( [ 'post_type' => POST_PARK, 'name' => basename( $path ), 'posts_per_page' => 1, 'post_status' => 'publish' ] );
		return $p ? (string) wp_parse_url( get_permalink( $p[0] ), PHP_URL_PATH ) : '';
	}

	/** The old finder and the old top-level pages: /park?province=GA&city=Decatur, /city?province=TX, /world-parks ... */
	public static function old_site_url( string $path, array $get ): string {
		$prov = strtolower( sanitize_text_field( $get['province'] ?? '' ) );
		$city = sanitize_text_field( $get['city'] ?? '' );
		switch ( $path ) {
			case 'park':
			case 'parks':
			case 'city':
				if ( preg_match( '/^[a-z]{2}$/', $prov ) ) {
					return '/' . $prov . ( $city ? '/' . legacy_slug( $city ) : '' ) . '/';
				}
				return '/states/';
			case 'multi-select':
				$acts = (array) ( $get['actitivity'] ?? $get['activity'] ?? [] );
				$a    = sanitize_text_field( reset( $acts ) ?: '' );
				return '/atlas/' . ( $a ? '?activity=' . rawurlencode( $a ) : '' );
			case 'united-states': return '/states/';
			case 'world-parks':   return '/world/';
			case 'photocontest':  return '/contest/';
			case 'acreage':       return '/acre-counter/';
			case 'map/node':      return '/atlas/';
			case 'contact-us':
			case 'contact':       return '/contact/';
			case 'blogtags':      return '/blog-tags/';
			case 'blog':          return '/blog/';
			case 'forum':
			case 'welcome':
			case 'try-again':
			case 'sorry':
			case 'node':          return '/';
			case 'photo-rules':   return '/rules/';
			case 'park-creation-contest':
			case 'park-adding-contest':
			case 'congrats':      return '/contest/';
			case 'acreage-message': return '/acre-counter/';
			case 'my-blog':       return '/blog/';
			case 'rss.xml':       return '/feed/';
			case 'sitemap.xml':   return '/wp-sitemap.xml';
			case 'about-us':      return '/about-us/';
			case 'new-user-register':
			case 'user/register': return wp_parse_url( wp_registration_url(), PHP_URL_PATH ) . '?' . wp_parse_url( wp_registration_url(), PHP_URL_QUERY );
			case 'user':
			case 'user/login':    return wp_parse_url( wp_login_url(), PHP_URL_PATH );
		}
		return '';
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
