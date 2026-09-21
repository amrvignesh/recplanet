<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Members upload photos, vote and enter contests. They never touch parks.
 * Editors and administrators do everything.
 */
class Roles {

	public static function install(): void {
		$park    = self::caps( 'rp_park', 'rp_parks' );
		$photo   = self::caps( 'rp_photo', 'rp_photos' );
		$contest = self::caps( 'rp_contest', 'rp_contests' );

		foreach ( [ 'administrator', 'editor' ] as $role ) {
			$r = get_role( $role );
			if ( ! $r ) {
				continue;
			}
			foreach ( array_merge( $park, $photo, $contest ) as $cap ) {
				$r->add_cap( $cap );
			}
		}

		remove_role( 'rp_member' );
		add_role( 'rp_member', 'Member', [
			'read'               => true,
			'upload_files'       => true,
			'edit_rp_photos'     => true,      // own photos only; map_meta_cap handles ownership
			'delete_rp_photos'   => true,
			'rp_vote'            => true,
			'rp_enter_contest'   => true,
		] );
		update_option( 'default_role', 'rp_member' );
	}

	private static function caps( string $s, string $p ): array {
		return [ "edit_$s", "read_$s", "delete_$s", "edit_$p", "edit_others_$p", "publish_$p", "read_private_$p", "delete_$p", "delete_private_$p", "delete_published_$p", "delete_others_$p", "edit_private_$p", "edit_published_$p" ];
	}
}
