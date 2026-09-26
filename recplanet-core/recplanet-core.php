<?php
/**
 * Plugin Name:       RecPlanet Core
 * Description:       Parks, photos, contests, the acre counter, the search index and the Drupal importer for recplanet.com. The theme draws it; this plugin owns the data.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            RecPlanet
 * Text Domain:       recplanet
 */

defined( 'ABSPATH' ) || exit;

define( 'RP_CORE_VERSION', '0.1.0' );
define( 'RP_CORE_FILE', __FILE__ );
define( 'RP_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once RP_CORE_DIR . 'inc/helpers.php';
require_once RP_CORE_DIR . 'inc/class-post-types.php';
require_once RP_CORE_DIR . 'inc/class-taxonomies.php';
require_once RP_CORE_DIR . 'inc/class-tables.php';
require_once RP_CORE_DIR . 'inc/class-index.php';
require_once RP_CORE_DIR . 'inc/class-counter.php';
require_once RP_CORE_DIR . 'inc/class-human-scale.php';
require_once RP_CORE_DIR . 'inc/class-settings.php';
require_once RP_CORE_DIR . 'inc/class-rest.php';
require_once RP_CORE_DIR . 'inc/class-rewrites.php';
require_once RP_CORE_DIR . 'inc/class-votes.php';
require_once RP_CORE_DIR . 'inc/class-freshness.php';
require_once RP_CORE_DIR . 'inc/class-messages.php';
require_once RP_CORE_DIR . 'inc/class-park-admin.php';
require_once RP_CORE_DIR . 'inc/class-draw.php';
require_once RP_CORE_DIR . 'inc/class-roles.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once RP_CORE_DIR . 'inc/cli/class-import-command.php';
}

register_activation_hook( __FILE__, function () {
	RP\Tables::install();
	RP\Roles::install();
	RP\Post_Types::register();
	RP\Taxonomies::register();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'rp_nightly_rebuild' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 03:15' ), 'daily', 'rp_nightly_rebuild' );
	}
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'rp_nightly_rebuild' );
	flush_rewrite_rules();
} );

add_action( 'plugins_loaded', function () {
	RP\Post_Types::init();
	RP\Taxonomies::init();
	RP\Index::init();
	RP\Counter::init();
	RP\Settings::init();
	RP\Rest::init();
	RP\Rewrites::init();
	RP\Votes::init();
	RP\Freshness::init();
	RP\Messages::init();
	RP\Park_Admin::init();
	RP\Draw::init();
	RP\Tables::maybe_upgrade();
} );
