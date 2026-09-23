<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Custom tables. Everything that has to be fast (maps, near-me, roll-ups) reads these, never postmeta.
 */
class Tables {

	const SCHEMA_VERSION = 4;

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		// One row per published park. lat/lng carry plain indexes so bbox queries work everywhere;
		// the POINT column gets a SPATIAL index where the server allows it (InnoDB on MariaDB 10.2+/MySQL 5.7+).
		dbDelta( "CREATE TABLE " . table( 'park_index' ) . " (
			post_id bigint(20) unsigned NOT NULL,
			lat decimal(9,6) DEFAULT NULL,
			lng decimal(9,6) DEFAULT NULL,
			acres decimal(20,2) DEFAULT NULL,
			country varchar(2) NOT NULL DEFAULT '',
			state varchar(8) NOT NULL DEFAULT '',
			county varchar(120) NOT NULL DEFAULT '',
			city varchar(120) NOT NULL DEFAULT '',
			steward_term bigint(20) unsigned NOT NULL DEFAULT 0,
			steward_level varchar(10) NOT NULL DEFAULT 'other',
			activity_bits bigint(20) unsigned NOT NULL DEFAULT 0,
			has_photo tinyint(1) NOT NULL DEFAULT 0,
			published_year smallint(5) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (post_id),
			KEY lat (lat),
			KEY lng (lng),
			KEY place (country,state,city),
			KEY county (country,state,county),
			KEY steward (steward_term),
			KEY acres (acres)
		) $charset;" );

		dbDelta( "CREATE TABLE " . table( 'acre_rollup' ) . " (
			scope varchar(10) NOT NULL,
			scope_key varchar(200) NOT NULL,
			park_count int(10) unsigned NOT NULL DEFAULT 0,
			acres decimal(22,2) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (scope,scope_key)
		) $charset;" );

		dbDelta( "CREATE TABLE " . table( 'acre_ledger' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			place varchar(200) NOT NULL DEFAULT '',
			event varchar(12) NOT NULL,
			delta_acres decimal(20,2) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset;" );

		dbDelta( "CREATE TABLE " . table( 'votes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			photo_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			voter_hash char(40) NOT NULL DEFAULT '',
			value tinyint(4) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_per_voter (photo_id,user_id,voter_hash),
			KEY photo (photo_id)
		) $charset;" );

		dbDelta( "CREATE TABLE " . table( 'freshness' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			park_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			voter_hash char(40) NOT NULL DEFAULT '',
			answer varchar(8) NOT NULL,
			note text,
			created_at datetime NOT NULL,
			read_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY park (park_id,created_at)
		) $charset;" );

		// Contact form messages: the site keeps every one, the admin Inbox shows them, unread first.
		dbDelta( "CREATE TABLE " . table( 'messages' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(120) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			subject varchar(120) NOT NULL DEFAULT '',
			message text,
			park_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			voter_hash char(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			read_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY unread (read_at,id)
		) $charset;" );

		dbDelta( "CREATE TABLE " . table( 'redirects' ) . " (
			old_path varchar(191) NOT NULL,
			new_path varchar(191) NOT NULL,
			kind varchar(16) NOT NULL DEFAULT '',
			hits int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (old_path)
		) $charset;" );

		// A POINT column is kept for engines that allow a SPATIAL index; WordPress.com's database refuses the
		// index, so every map query uses the plain lat/lng indexes and the column is informational only.
		$col = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = %s AND column_name = 'pt'", table( 'park_index' ) ) );
		if ( ! $col ) {
			$wpdb->query( "ALTER TABLE " . table( 'park_index' ) . " ADD COLUMN pt POINT NULL" );
		}

		update_option( 'rp_schema_version', self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( (int) get_option( 'rp_schema_version', 0 ) < self::SCHEMA_VERSION ) {
			self::install();
		}
	}
}
