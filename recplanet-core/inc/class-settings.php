<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > RecPlanet. Holds the Google Maps Platform key (never in code), counter display,
 * contest defaults and the embed widget's allowed origins.
 */
class Settings {

	const GROUP = 'recplanet';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
	}

	public static function menu(): void {
		add_options_page( 'RecPlanet', 'RecPlanet', 'manage_options', 'recplanet', [ __CLASS__, 'render' ] );
	}

	public static function register(): void {
		register_setting( self::GROUP, 'rp_google_maps_key', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => trim( sanitize_text_field( $v ) ), 'default' => '' ] );
		register_setting( self::GROUP, 'rp_google_map_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( self::GROUP, 'rp_counter_headline', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => in_array( $v, [ 'world', 'us' ], true ) ? $v : 'world', 'default' => 'world' ] );
		register_setting( self::GROUP, 'rp_contest_days', [ 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30 ] );
		register_setting( self::GROUP, 'rp_photo_needs_approval', [ 'type' => 'boolean', 'sanitize_callback' => fn( $v ) => (bool) $v, 'default' => true ] );
		register_setting( self::GROUP, 'rp_embed_origins', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );

		add_settings_section( 'rp_maps', 'Google Maps Platform', fn() => print '<p>The key is stored here and injected into pages at render time. Restrict it to your domain in the Google Cloud console. Enable Maps JavaScript, Static Maps, Street View, Places, Routes, Distance Matrix, Geocoding and Elevation.</p>', 'recplanet' );
		add_settings_field( 'rp_google_maps_key', 'API key', fn() => self::text( 'rp_google_maps_key', 'password' ), 'recplanet', 'rp_maps' );
		add_settings_field( 'rp_google_map_id', 'Map ID (cloud style)', fn() => self::text( 'rp_google_map_id' ), 'recplanet', 'rp_maps' );

		add_settings_section( 'rp_counter', 'Acre counter', fn() => print '<p>The counter is always a live sum of published parks. This only chooses which figure leads.</p>', 'recplanet' );
		add_settings_field( 'rp_counter_headline', 'Headline figure', function () {
			$v = get_option( 'rp_counter_headline', 'world' );
			echo '<label><input type="radio" name="rp_counter_headline" value="world" ' . checked( $v, 'world', false ) . '> World total, as on the old site</label><br>';
			echo '<label><input type="radio" name="rp_counter_headline" value="us" ' . checked( $v, 'us', false ) . '> United States, with the world total beneath</label>';
		}, 'recplanet', 'rp_counter' );

		add_settings_section( 'rp_contest', 'Contest and photos', '__return_null', 'recplanet' );
		add_settings_field( 'rp_contest_days', 'Default contest length (days)', fn() => self::text( 'rp_contest_days', 'number' ), 'recplanet', 'rp_contest' );
		add_settings_field( 'rp_photo_needs_approval', 'Uploads', fn() => print '<label><input type="checkbox" name="rp_photo_needs_approval" value="1" ' . checked( get_option( 'rp_photo_needs_approval', true ), true, false ) . '> An editor approves photos before they show</label>', 'recplanet', 'rp_contest' );

		add_settings_section( 'rp_embed', 'Embeddable counter', fn() => print '<p>One origin per line, for example https://example.org. Leave empty to allow any site to embed the counter.</p>', 'recplanet' );
		add_settings_field( 'rp_embed_origins', 'Allowed origins', fn() => print '<textarea name="rp_embed_origins" rows="4" cols="50">' . esc_textarea( get_option( 'rp_embed_origins', '' ) ) . '</textarea>', 'recplanet', 'rp_embed' );
	}

	private static function text( string $name, string $type = 'text' ): void {
		printf( '<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off">', esc_attr( $type ), esc_attr( $name ), esc_attr( get_option( $name, '' ) ) );
	}

	public static function render(): void {
		$drift = get_option( 'rp_counter_drift' );
		echo '<div class="wrap"><h1>RecPlanet</h1>';
		$w = Counter::get( 'world', 'world' );
		echo '<p>Counter now: <strong>' . esc_html( format_acres( $w['acres'] ) ) . '</strong> acres across <strong>' . esc_html( number_format( $w['count'] ) ) . '</strong> published parks.</p>';
		if ( $drift ) {
			echo '<div class="notice notice-warning"><p>The nightly re-sum found drift on ' . esc_html( $drift['when'] ) . ': ' . esc_html( format_acres( $drift['before']['acres'] ) ) . ' became ' . esc_html( format_acres( $drift['after']['acres'] ) ) . '. The rebuilt figure is the correct one.</p></div>';
		}
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		do_settings_sections( 'recplanet' );
		submit_button();
		echo '</form></div>';
	}

	public static function maps_key(): string {
		return (string) get_option( 'rp_google_maps_key', '' );
	}
}
