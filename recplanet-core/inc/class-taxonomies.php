<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * activity  flat, the 45 fixed values
 * facility  flat, ~80 types parsed from the old Park Tags (playgrounds, basketball courts ...)
 * steward   two levels: level (city, county, state, federal, tribal, other) > name
 * place     four levels: country > state > county > city
 */
class Taxonomies {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'init', [ __CLASS__, 'seed_activities' ], 20 );
	}

	public static function register(): void {
		register_taxonomy( TAX_ACTIVITY, POST_PARK, [
			'labels' => self::labels( 'Activity', 'Activities' ), 'hierarchical' => false, 'public' => true,
			'rewrite' => [ 'slug' => 'activities', 'with_front' => false ], 'show_in_rest' => true, 'show_admin_column' => true,
		] );
		register_taxonomy( TAX_FACILITY, POST_PARK, [
			'labels' => self::labels( 'Facility', 'Facilities' ), 'hierarchical' => false, 'public' => true,
			'rewrite' => [ 'slug' => 'facilities', 'with_front' => false ], 'show_in_rest' => true,
		] );
		register_taxonomy( TAX_STEWARD, POST_PARK, [
			'labels' => self::labels( 'Steward', 'Stewards' ), 'hierarchical' => true, 'public' => true,
			'rewrite' => [ 'slug' => 'managed-by', 'with_front' => false, 'hierarchical' => false ], 'show_in_rest' => true, 'show_admin_column' => true,
		] );
		register_taxonomy( TAX_PLACE, POST_PARK, [
			'labels' => self::labels( 'Place', 'Places' ), 'hierarchical' => true, 'public' => true,
			'rewrite' => false,                       // /{state}, /{state}/{city}: see Rewrites.
			'query_var' => 'rp_place', 'show_in_rest' => true, 'show_admin_column' => true,
		] );
	}

	/** The 45 activities exist from day one so the editor sees the same list Drupal had. */
	public static function seed_activities(): void {
		if ( get_option( 'rp_activities_seeded' ) === RP_CORE_VERSION ) {
			return;
		}
		foreach ( ACTIVITIES as $name ) {
			if ( ! term_exists( $name, TAX_ACTIVITY ) ) {
				wp_insert_term( $name, TAX_ACTIVITY );
			}
		}
		foreach ( STEWARD_LEVELS as $slug => $label ) {
			if ( ! term_exists( $slug, TAX_STEWARD ) ) {
				wp_insert_term( $label, TAX_STEWARD, [ 'slug' => $slug ] );
			}
		}
		update_option( 'rp_activities_seeded', RP_CORE_VERSION );
	}

	/**
	 * Find or create the place chain country > state > county > city and return the leaf term id.
	 * Codes are kept in term meta so the chain can be rebuilt from Drupal's location rows.
	 */
	public static function place_term( string $country, string $state = '', string $county = '', string $city = '' ): int {
		$parent = 0;
		$chain  = [
			[ 'country', strtolower( $country ), self::country_name( $country ) ],
			[ 'state', strtoupper( $state ), self::state_name( $country, $state ) ],
			[ 'county', $county, $county ],
			[ 'city', $city, $city ],
		];
		foreach ( $chain as [ $level, $code, $name ] ) {
			if ( '' === $code ) {
				break;
			}
			$slug = ( 'country' === $level ) ? $code : ( 'state' === $level ? strtolower( $code ) : legacy_slug( $name ) );
			// Looked up by name under the parent: WordPress keeps term slugs unique across the whole taxonomy, so the
			// second Springfield gets a slug like springfield-union-county and a slug lookup would miss it.
			$term = get_terms( [ 'taxonomy' => TAX_PLACE, 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ] );
			if ( $term && ! is_wp_error( $term ) ) {
				$parent = $term[0]->term_id;
				if ( '' === (string) get_term_meta( $parent, 'rp_slug', true ) ) {
					update_term_meta( $parent, 'rp_slug', $slug );
				}
				continue;
			}
			$r = wp_insert_term( $name, TAX_PLACE, [ 'slug' => $slug, 'parent' => $parent ] );
			if ( is_wp_error( $r ) ) {
				return $parent;
			}
			$parent = $r['term_id'];
			update_term_meta( $parent, 'rp_level', $level );
			update_term_meta( $parent, 'rp_code', $code );
			update_term_meta( $parent, 'rp_slug', $slug );     // the URL segment, which may differ from the term's own slug
		}
		return $parent;
	}

	/** Find or create the steward chain level > name and return the name term id. */
	public static function steward_term( string $level, string $name ): int {
		$level = array_key_exists( $level, STEWARD_LEVELS ) ? $level : 'other';
		$lt    = term_exists( $level, TAX_STEWARD );
		$lid   = $lt ? (int) $lt['term_id'] : (int) wp_insert_term( STEWARD_LEVELS[ $level ], TAX_STEWARD, [ 'slug' => $level ] )['term_id'];
		$name  = trim( $name ) ?: STEWARD_LEVELS[ $level ];
		$slug  = legacy_slug( $level . '-' . $name );
		$t     = get_terms( [ 'taxonomy' => TAX_STEWARD, 'slug' => $slug, 'hide_empty' => false, 'number' => 1 ] );
		if ( $t && ! is_wp_error( $t ) ) {
			return $t[0]->term_id;
		}
		$r = wp_insert_term( $name, TAX_STEWARD, [ 'slug' => $slug, 'parent' => $lid ] );
		return is_wp_error( $r ) ? $lid : (int) $r['term_id'];
	}

	/** "City of Irving" -> [city, City of Irving]; "Montgomery County" -> [county, ...]; etc. */
	public static function classify_ownership( string $o ): array {
		$o = trim( preg_replace( '/\s+/', ' ', $o ) );
		if ( '' === $o ) {
			return [ 'other', '' ];
		}
		if ( preg_match( '/^(City|Town|Village|Borough|Township) of /i', $o ) ) {
			return [ 'city', $o ];
		}
		if ( preg_match( '/\bCounty\b|\bParish\b/i', $o ) ) {
			return [ 'county', $o ];
		}
		if ( preg_match( '/^(State|Commonwealth) of /i', $o ) ) {
			return [ 'state', $o ];
		}
		if ( preg_match( '/United States|U\.?S\.? (Government|Forest|Fish|Army|Navy|Air Force)|Federal|National Park Service|Bureau of|Forest Service/i', $o ) ) {
			return [ 'federal', $o ];
		}
		if ( preg_match( '/Tribe|Tribal|Nation\b|Reservation/i', $o ) ) {
			return [ 'tribal', $o ];
		}
		return [ 'other', $o ];
	}

	private static function country_name( string $code ): string {
		$names = [ 'us' => 'United States', 'ca' => 'Canada', 'mx' => 'Mexico', 'es' => 'Spain', 'uk' => 'United Kingdom', 'in' => 'India', 'br' => 'Brazil', 'cn' => 'China', 'za' => 'South Africa', 'au' => 'Australia', 'cr' => 'Costa Rica', 'aq' => 'Antarctica', 'gl' => 'Greenland' ];
		return $names[ strtolower( $code ) ] ?? strtoupper( $code );
	}

	private static function state_name( string $country, string $state ): string {
		if ( 'us' === strtolower( $country ) ) {
			return us_states()[ strtoupper( $state ) ] ?? $state;
		}
		return $state;
	}

	private static function labels( string $s, string $p ): array {
		return [ 'name' => $p, 'singular_name' => $s, 'menu_name' => $p, 'all_items' => "All $p", 'edit_item' => "Edit $s", 'add_new_item' => "Add $s", 'search_items' => "Search $p" ];
	}
}
