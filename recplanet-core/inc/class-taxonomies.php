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
	 * The place terms a park belongs to: its city (or county, or state when nothing finer is known), plus its
	 * county as a second term when it has both. Cities sit under their county when the record names one,
	 * otherwise directly under the state; a city is found under either, so one city is one term.
	 * Codes are kept in term meta so the chain can be rebuilt from Drupal's location rows.
	 */
	public static function place_terms( string $country, string $state = '', string $county = '', string $city = '' ): array {
		$country = strtolower( $country ) ?: 'us';
		$cid     = self::find_or_create( self::country_name( $country ), $country, 0, 'country', $country );
		if ( ! $cid ) {
			return [];
		}
		$state = strtoupper( trim( $state ) );
		if ( '' === $state ) {
			return [ $cid ];
		}
		$sid = self::find_or_create( self::state_name( $country, $state ), strtolower( $state ), $cid, 'state', $state );
		if ( ! $sid ) {
			return [ $cid ];
		}
		$county   = trim( $county );
		$city     = trim( $city );
		$county_id = '' !== $county ? self::find_or_create( $county, legacy_slug( $county ), $sid, 'county', $county ) : 0;
		if ( '' === $city ) {
			return [ $county_id ?: $sid ];
		}
		$city_id = 0;
		foreach ( get_terms( [ 'taxonomy' => TAX_PLACE, 'name' => $city, 'hide_empty' => false ] ) ?: [] as $t ) {
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			$p = $t->parent ? get_term( $t->parent, TAX_PLACE ) : null;
			if ( $t->parent === $sid || ( $p instanceof \WP_Term && $p->parent === $sid ) ) {
				$city_id = $t->term_id;
				break;
			}
		}
		if ( ! $city_id ) {
			$city_id = self::find_or_create( $city, legacy_slug( $city ), $county_id ?: $sid, 'city', $city );
		}
		$out = [ $city_id ?: $county_id ?: $sid ];
		if ( $city_id && $county_id ) {
			$out[] = $county_id;
		}
		return $out;
	}

	/** The leaf place term for a park. */
	public static function place_term( string $country, string $state = '', string $county = '', string $city = '' ): int {
		return (int) ( self::place_terms( $country, $state, $county, $city )[0] ?? 0 );
	}

	/** A place term by name under a parent, created when missing. Term slugs are unique site-wide, so the URL segment lives in meta. */
	private static function find_or_create( string $name, string $slug, int $parent, string $level, string $code ): int {
		$term = get_terms( [ 'taxonomy' => TAX_PLACE, 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ] );
		if ( $term && ! is_wp_error( $term ) ) {
			$id = $term[0]->term_id;
			if ( '' === (string) get_term_meta( $id, 'rp_slug', true ) ) {
				update_term_meta( $id, 'rp_slug', $slug );
			}
			return $id;
		}
		$r = wp_insert_term( $name, TAX_PLACE, [ 'slug' => $slug, 'parent' => $parent ] );
		if ( is_wp_error( $r ) ) {
			return 0;
		}
		update_term_meta( $r['term_id'], 'rp_level', $level );
		update_term_meta( $r['term_id'], 'rp_code', $code );
		update_term_meta( $r['term_id'], 'rp_slug', $slug );
		return (int) $r['term_id'];
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
