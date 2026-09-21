<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Parks, photos and contests.
 * Parks are editorial-only: only users with edit_rp_parks (editors, admins) can touch them.
 */
class Post_Types {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
	}

	public static function register(): void {
		register_post_type( POST_PARK, [
			'labels'       => self::labels( 'Park', 'Parks' ),
			'public'       => true,
			'has_archive'  => false,
			'rewrite'      => false,                 // URLs are /{state}/{city}/{slug}; see Rewrites.
			'query_var'    => 'rp_park',
			'menu_icon'    => 'dashicons-palmtree',
			'menu_position'=> 5,
			'supports'     => [ 'title', 'editor', 'thumbnail', 'revisions', 'excerpt' ],
			'show_in_rest' => true,
			'taxonomies'   => [ TAX_ACTIVITY, TAX_FACILITY, TAX_STEWARD, TAX_PLACE ],
			'capability_type' => [ 'rp_park', 'rp_parks' ],
			'map_meta_cap' => true,
		] );

		register_post_type( POST_PHOTO, [
			'labels'       => self::labels( 'Photo', 'Photos' ),
			'public'       => true,
			'has_archive'  => 'photos',
			'rewrite'      => [ 'slug' => 'photos', 'with_front' => false ],
			'menu_icon'    => 'dashicons-camera',
			'menu_position'=> 6,
			'supports'     => [ 'title', 'editor', 'thumbnail', 'author', 'comments' ],
			'show_in_rest' => true,
			'capability_type' => [ 'rp_photo', 'rp_photos' ],
			'map_meta_cap' => true,
		] );

		register_post_type( POST_CONTEST, [
			'labels'       => self::labels( 'Contest', 'Contests' ),
			'public'       => true,
			'has_archive'  => 'contest',
			'rewrite'      => [ 'slug' => 'contest', 'with_front' => false ],
			'menu_icon'    => 'dashicons-awards',
			'menu_position'=> 7,
			'supports'     => [ 'title', 'editor', 'thumbnail', 'revisions' ],
			'show_in_rest' => true,
			'capability_type' => [ 'rp_contest', 'rp_contests' ],
			'map_meta_cap' => true,
		] );
	}

	public static function register_meta(): void {
		$park = [
			'rp_acreage'      => 'number',
			'rp_website'      => 'string',
			'rp_street'       => 'string',
			'rp_postal'       => 'string',
			'rp_lat'          => 'number',
			'rp_lng'          => 'number',
			'rp_country'      => 'string',   // ISO code, lower case, as in Drupal (us, ca ...)
			'rp_state'        => 'string',   // code, upper case (TX)
			'rp_county'       => 'string',
			'rp_city'         => 'string',
			'rp_verified_on'  => 'string',   // Y-m-d
			'rp_legacy_nid'   => 'integer',
			'rp_legacy_path'  => 'string',
			'rp_meta_description' => 'string',
			'rp_activity_suggestions' => 'string', // JSON list awaiting bulk approval
		];
		foreach ( $park as $key => $type ) {
			register_post_meta( POST_PARK, $key, [
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => fn() => current_user_can( 'edit_rp_parks' ),
			] );
		}

		$photo = [
			'rp_park_id'      => 'integer',
			'rp_contest_id'   => 'integer',
			'rp_contributor'  => 'string',   // legacy display name when there is no WP user
			'rp_taken_on'     => 'string',
			'rp_votes'        => 'integer',  // cached from rp_votes
			'rp_score'        => 'number',
			'rp_legacy_nid'   => 'integer',
			'rp_legacy_votes' => 'integer',  // frozen Drupal total
		];
		foreach ( $photo as $key => $type ) {
			register_post_meta( POST_PHOTO, $key, [ 'type' => $type, 'single' => true, 'show_in_rest' => true,
				'auth_callback' => fn() => current_user_can( 'edit_rp_photos' ) ] );
		}

		$contest = [
			'rp_opens_at'        => 'string',
			'rp_closes_at'       => 'string',
			'rp_voting_closes_at'=> 'string',
			'rp_prizes'          => 'string',
			'rp_winners'         => 'string',   // JSON list of photo ids
			'rp_status'          => 'string',   // draft | open | voting | closed
		];
		foreach ( $contest as $key => $type ) {
			register_post_meta( POST_CONTEST, $key, [ 'type' => $type, 'single' => true, 'show_in_rest' => true,
				'auth_callback' => fn() => current_user_can( 'edit_rp_contests' ) ] );
		}
	}

	private static function labels( string $s, string $p ): array {
		return [
			'name' => $p, 'singular_name' => $s, 'add_new_item' => "Add $s", 'edit_item' => "Edit $s",
			'new_item' => "New $s", 'view_item' => "View $s", 'search_items' => "Search $p",
			'not_found' => "No $p found", 'all_items' => "All $p", 'menu_name' => $p,
		];
	}
}
