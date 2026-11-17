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
		add_action( 'saved_' . TAX_STEWARD, [ __CLASS__, 'steward_saved' ], 10, 3 );
	}

	public static function register(): void {
		register_taxonomy( TAX_ACTIVITY, POST_PARK, [
			'labels' => self::labels( 'Activity', 'Activities' ), 'hierarchical' => false, 'public' => true,
			'rewrite' => [ 'slug' => 'activities', 'with_front' => false ], 'show_in_rest' => true, 'show_admin_column' => true,
			'meta_box_cb' => 'post_categories_meta_box', 'show_in_quick_edit' => false,   // a checklist as on the old site; Quick Edit's text box would wipe it
		] );
		register_taxonomy( TAX_FACILITY, POST_PARK, [
			'labels' => self::labels( 'Facility', 'Facilities' ), 'hierarchical' => false, 'public' => true,
			'rewrite' => [ 'slug' => 'facilities', 'with_front' => false ], 'show_in_rest' => true,
			'meta_box_cb' => 'post_categories_meta_box', 'show_in_quick_edit' => false,
		] );
		register_taxonomy( TAX_STEWARD, POST_PARK, [
			'labels' => self::labels( 'Steward', 'Stewards' ), 'hierarchical' => true, 'public' => true,
			'rewrite' => [ 'slug' => 'managed-by', 'with_front' => false, 'hierarchical' => false ], 'show_in_rest' => true, 'show_admin_column' => true,
			'meta_box_cb' => false, 'show_in_quick_edit' => false,   // thousands of names: Park_Admin's Managed by box finds one by typing
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

	/**
	 * A steward saved with no level above it (the Stewards screen, or the old checklist's "Add new") goes under the
	 * level its name implies, with the slug the importer would have given it, so the same name is never on file twice.
	 */
	public static function steward_saved( int $term_id, int $tt_id, bool $update ): void {
		static $busy = false;
		if ( $busy ) {
			return;
		}
		$busy = true;
		self::adopt_steward( $term_id );
		$busy = false;
	}

	/**
	 * Put a parentless steward under its level and re-index its parks. Returns the id the steward lives at afterwards:
	 * the same term, re-parented and re-slugged, or the term that already had that name, when the parks moved across to
	 * it. Level terms and stewards that already have a level are returned unchanged.
	 */
	public static function adopt_steward( int $term_id ): int {
		$t = get_term( $term_id, TAX_STEWARD );
		if ( ! $t instanceof \WP_Term || $t->parent || array_key_exists( $t->slug, STEWARD_LEVELS ) ) {
			return $term_id;
		}
		[ $level ] = self::classify_ownership( $t->name );
		$lt    = term_exists( $level, TAX_STEWARD );
		$lid   = $lt ? (int) $lt['term_id'] : (int) wp_insert_term( STEWARD_LEVELS[ $level ], TAX_STEWARD, [ 'slug' => $level ] )['term_id'];
		$slug  = legacy_slug( $level . '-' . $t->name );
		$parks = get_objects_in_term( $term_id, TAX_STEWARD );
		$parks = is_array( $parks ) ? array_map( 'intval', $parks ) : [];
		$dup   = get_term_by( 'slug', $slug, TAX_STEWARD );
		$into  = $dup instanceof \WP_Term && (int) $dup->term_id !== $term_id ? (int) $dup->term_id : 0;
		if ( ! $into ) {
			$r = wp_update_term( $term_id, TAX_STEWARD, [ 'parent' => $lid, 'slug' => $slug ] );
			if ( is_wp_error( $r ) ) {
				$ex   = term_exists( $t->name, TAX_STEWARD, $lid );        // same name already under that level with another slug
				$into = $ex ? (int) $ex['term_id'] : 0;
			}
		}
		if ( $into ) {
			foreach ( $parks as $pid ) {
				wp_set_object_terms( $pid, [ $into ], TAX_STEWARD );
			}
			wp_delete_term( $term_id, TAX_STEWARD );
			$term_id = $into;
		}
		foreach ( $parks as $pid ) {
			if ( 'publish' === get_post_status( $pid ) ) {
				Index::upsert( $pid );
			}
		}
		return $term_id;
	}

	/** Every parentless steward that is not a level, adopted. Returns [adopted, merged into an existing steward]. */
	public static function repair_stewards( ?callable $log = null ): array {
		$n = $merged = 0;
		foreach ( get_terms( [ 'taxonomy' => TAX_STEWARD, 'parent' => 0, 'hide_empty' => false ] ) as $t ) {
			if ( array_key_exists( $t->slug, STEWARD_LEVELS ) ) {
				continue;
			}
			$n++;
			$to = self::adopt_steward( (int) $t->term_id );
			if ( $to !== (int) $t->term_id ) {
				$merged++;
			}
			if ( $log ) {
				$log( $t->name, $to );
			}
		}
		return [ $n, $merged ];
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
		if ( preg_match( '/\b(City|Town|Village|Borough|Township)$/i', $o ) ) {      // "Yeadon Borough", "Saginaw Charter Township"
			return [ 'city', $o ];
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
		return \RP\country_name( $code );
	}

	private static function state_name( string $country, string $state ): string {
		return region_name( $country, $state );
	}

	private static function labels( string $s, string $p ): array {
		return [ 'name' => $p, 'singular_name' => $s, 'menu_name' => $p, 'all_items' => "All $p", 'edit_item' => "Edit $s", 'add_new_item' => "Add $s", 'search_items' => "Search $p" ];
	}
}
