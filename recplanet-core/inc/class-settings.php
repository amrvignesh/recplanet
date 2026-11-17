<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > RecPlanet. Holds the Google Maps Platform key (never in code), counter display,
 * contest defaults and the embed widget's allowed origins.
 */
class Settings {

	const GROUP = 'recplanet';

	/** The old site's header message board, with its links pointed at the new pages. */
	const DEFAULT_BOARD = 'Welcome to RecPlanet. Visit the <a href="/contest/">contest</a> page for over <strong>$4000</strong> in prizes. You can win a prize simply by voting (membership not required to vote). Search our database for info on thousands of parks. Blog for royalties, use the <a href="/contact/">Contact Us</a> link for more information.';

	/** The header message board's HTML, or an empty string when it is switched off or empty. */
	public static function board(): string {
		if ( ! get_option( 'rp_board_on', true ) ) {
			return '';
		}
		return trim( (string) get_option( 'rp_board_text', self::DEFAULT_BOARD ) );
	}

	const CAP = 'edit_rp_parks';     // editors run the site day to day, so the settings are theirs too

	const DEFAULT_VOTE_MESSAGE = '<p>You are voter #{n}.</p><p>Keep voting on other photos to be a winner! The winning voter sees a code on this screen the moment they vote, so there is no screenshot to take. Please note that AdBlock users will not receive prizes.</p>';
	const DEFAULT_FACEBOOK     = 'https://www.facebook.com/pages/RecPlanet/127298557845';
	/** Ad slots the templates know. Code for each is pasted under Settings › Ads; an empty slot prints nothing. */
	const AD_SLOTS = [ 'vote_dialog' => 'Beside the message after a vote', 'sidebar' => 'Park page, side column', 'park_page_bottom' => 'Park page, foot' ];

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 11 );     // after the RecPlanet menu exists
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
		add_filter( 'option_page_capability_' . self::GROUP, fn() => self::CAP );
	}

	public static function menu(): void {
		add_submenu_page( 'rp-inbox', 'RecPlanet settings', 'Settings', self::CAP, 'rp-settings', [ __CLASS__, 'render' ] );
		// The old address under Settings keeps working for administrators: it forwards to the page above.
		$hook = add_options_page( 'RecPlanet', 'RecPlanet', 'manage_options', 'recplanet', '__return_null' );
		add_action( 'load-' . $hook, function () {
			wp_safe_redirect( admin_url( 'admin.php?page=rp-settings' ) );
			exit;
		} );
	}

	public static function register(): void {
		register_setting( self::GROUP, 'rp_google_maps_key', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => trim( sanitize_text_field( $v ) ), 'default' => '' ] );
		register_setting( self::GROUP, 'rp_google_map_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( self::GROUP, 'rp_counter_headline', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => in_array( $v, [ 'world', 'us' ], true ) ? $v : 'world', 'default' => 'world' ] );
		register_setting( self::GROUP, 'rp_contest_days', [ 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30 ] );
		register_setting( self::GROUP, 'rp_photo_needs_approval', [ 'type' => 'boolean', 'sanitize_callback' => fn( $v ) => (bool) $v, 'default' => true ] );
		register_setting( self::GROUP, 'rp_board_on', [ 'type' => 'boolean', 'sanitize_callback' => fn( $v ) => (bool) $v, 'default' => true ] );
		register_setting( self::GROUP, 'rp_board_text', [ 'type' => 'string', 'sanitize_callback' => 'wp_kses_post', 'default' => self::DEFAULT_BOARD ] );
		register_setting( self::GROUP, 'rp_home_text', [ 'type' => 'string', 'sanitize_callback' => 'wp_kses_post', 'default' => '' ] );
		register_setting( self::GROUP, 'rp_contact_email', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '' ] );
		register_setting( self::GROUP, 'rp_turnstile_site', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => trim( sanitize_text_field( $v ) ), 'default' => '' ] );
		register_setting( self::GROUP, 'rp_turnstile_secret', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => trim( sanitize_text_field( $v ) ), 'default' => '' ] );
		register_setting( self::GROUP, 'rp_embed_origins', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( self::GROUP, 'rp_geocoding_key', [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => trim( sanitize_text_field( (string) $v ) ), 'default' => '' ] );
		register_setting( self::GROUP, 'rp_vote_message', [ 'type' => 'string', 'sanitize_callback' => 'wp_kses_post', 'default' => self::DEFAULT_VOTE_MESSAGE ] );
		register_setting( self::GROUP, 'rp_facebook_url', [ 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => self::DEFAULT_FACEBOOK ] );
		foreach ( array_merge( [ 'rp_ads_loader' ], array_map( fn( $s ) => "rp_ad_$s", array_keys( self::AD_SLOTS ) ) ) as $opt ) {
			// Raw ad code is script and markup: only a user with unfiltered_html may change it; anyone else's save keeps the stored value.
			register_setting( self::GROUP, $opt, [ 'type' => 'string', 'sanitize_callback' => fn( $v ) => current_user_can( 'unfiltered_html' ) ? trim( (string) $v ) : (string) get_option( $opt, '' ), 'default' => '' ] );
		}

		add_settings_section( 'rp_maps', 'Google Maps Platform', fn() => print '<p>The key is stored here and injected into pages at render time. Restrict it to your domain in the Google Cloud console. Enable Maps JavaScript, Static Maps, Street View, Places, Routes, Distance Matrix, Geocoding and Elevation.</p>', 'recplanet' );
		add_settings_field( 'rp_google_maps_key', 'API key', fn() => self::text( 'rp_google_maps_key', 'password' ), 'recplanet', 'rp_maps' );
		add_settings_field( 'rp_google_map_id', 'Map ID (cloud style)', fn() => self::text( 'rp_google_map_id' ), 'recplanet', 'rp_maps' );
		add_settings_field( 'rp_geocoding_key', 'Geocoding key (server)', function () {
			self::text( 'rp_geocoding_key', 'password' );
			echo '<p class="description">For the park editor&rsquo;s "Find by address" button, which asks Google from the server. A key restricted to this website is refused for server calls, so make a second key with only the Geocoding API enabled and no website restriction. Empty: the button tries the key above.</p>';
		}, 'recplanet', 'rp_maps' );

		add_settings_section( 'rp_counter', 'Acre counter', fn() => print '<p>The counter is always a live sum of published parks. This only chooses which figure leads.</p>', 'recplanet' );
		add_settings_field( 'rp_counter_headline', 'Headline figure', function () {
			$v = get_option( 'rp_counter_headline', 'world' );
			echo '<label><input type="radio" name="rp_counter_headline" value="world" ' . checked( $v, 'world', false ) . '> World total, as on the old site</label><br>';
			echo '<label><input type="radio" name="rp_counter_headline" value="us" ' . checked( $v, 'us', false ) . '> United States, with the world total beneath</label>';
		}, 'recplanet', 'rp_counter' );

		add_settings_section( 'rp_contest', 'Contest and photos', '__return_null', 'recplanet' );
		add_settings_field( 'rp_contest_days', 'Default contest length (days)', fn() => self::text( 'rp_contest_days', 'number' ), 'recplanet', 'rp_contest' );
		add_settings_field( 'rp_photo_needs_approval', 'Uploads', fn() => print '<label><input type="checkbox" name="rp_photo_needs_approval" value="1" ' . checked( get_option( 'rp_photo_needs_approval', true ), true, false ) . '> An editor approves photos before they show</label>', 'recplanet', 'rp_contest' );
		add_settings_field( 'rp_vote_message', 'Message after a vote', fn() => print '<textarea name="rp_vote_message" rows="5" class="large-text">' . esc_textarea( get_option( 'rp_vote_message', self::DEFAULT_VOTE_MESSAGE ) ) . '</textarea><p class="description">Opens in a window the moment a visitor rates a photo while a contest is taking votes. {n} becomes the voter number. Paragraphs, links and bold allowed. A winner sees their code above this text; the Facebook button and the ad slot sit beside it.</p>', 'recplanet', 'rp_contest' );
		add_settings_field( 'rp_facebook_url', 'Facebook page', fn() => self::text( 'rp_facebook_url', 'url' ), 'recplanet', 'rp_contest' );

		add_settings_section( 'rp_board', 'Message board', fn() => print '<p>The short notice in the header under the logo, on every page, as on the old site. A sentence or two; links and bold allowed. Leave the box empty or untick to hide it.</p>', 'recplanet' );
		add_settings_field( 'rp_board_on', 'Show', fn() => print '<label><input type="checkbox" name="rp_board_on" value="1" ' . checked( get_option( 'rp_board_on', true ), true, false ) . '> Show the message board</label>', 'recplanet', 'rp_board' );
		add_settings_field( 'rp_board_text', 'Message', fn() => print '<textarea name="rp_board_text" rows="4" class="large-text">' . esc_textarea( get_option( 'rp_board_text', self::DEFAULT_BOARD ) ) . '</textarea>', 'recplanet', 'rp_board' );

		add_settings_section( 'rp_home', 'Home page text', fn() => print '<p>Shown at the foot of the home page under "About this database, in the founder&rsquo;s words". Plain paragraphs; links allowed.</p>', 'recplanet' );
		add_settings_field( 'rp_home_text', 'Text', fn() => print '<textarea name="rp_home_text" rows="14" class="large-text">' . esc_textarea( get_option( 'rp_home_text', '' ) ) . '</textarea>', 'recplanet', 'rp_home' );

		add_settings_section( 'rp_contact', 'Contact form', fn() => print '<p>Every message is kept in RecPlanet &rsaquo; Inbox and also emailed. The form has a honeypot, a timing check and a limit of five messages an hour per visitor. Without Turnstile keys it asks a simple sum; with them it shows Cloudflare Turnstile, which is free at <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com</a>.</p>', 'recplanet' );
		add_settings_field( 'rp_contact_email', 'Send messages to', function () { self::text( 'rp_contact_email', 'email' ); echo '<p class="description">Empty means the site admin email, ' . esc_html( get_option( 'admin_email' ) ) . '.</p>'; }, 'recplanet', 'rp_contact' );
		add_settings_field( 'rp_turnstile_site', 'Turnstile site key', fn() => self::text( 'rp_turnstile_site' ), 'recplanet', 'rp_contact' );
		add_settings_field( 'rp_turnstile_secret', 'Turnstile secret key', fn() => self::text( 'rp_turnstile_secret', 'password' ), 'recplanet', 'rp_contact' );

		if ( current_user_can( 'unfiltered_html' ) ) {
			add_settings_section( 'rp_ads', 'Ads', fn() => print '<p>Paste what Google AdSense gives you: the loader script once, then one ad unit (the <code>&lt;ins&gt;</code> block, without a second loader) per slot. An empty slot prints nothing, so the site runs without ads until the account is approved. Administrators only.</p>', 'recplanet' );
			add_settings_field( 'rp_ads_loader', 'Loader script', fn() => print '<textarea name="rp_ads_loader" rows="3" class="large-text code">' . esc_textarea( get_option( 'rp_ads_loader', '' ) ) . '</textarea>', 'recplanet', 'rp_ads' );
			foreach ( self::AD_SLOTS as $slot => $label ) {
				add_settings_field( "rp_ad_$slot", $label, fn() => print '<textarea name="rp_ad_' . esc_attr( $slot ) . '" rows="4" class="large-text code">' . esc_textarea( get_option( "rp_ad_$slot", '' ) ) . '</textarea>', 'recplanet', 'rp_ads' );
			}
		}

		add_settings_section( 'rp_embed', 'Embeddable counter', fn() => print '<p>One origin per line, for example https://example.org. Leave empty to allow any site to embed the counter.</p>', 'recplanet' );
		add_settings_field( 'rp_embed_origins', 'Allowed origins', fn() => print '<textarea name="rp_embed_origins" rows="4" cols="50">' . esc_textarea( get_option( 'rp_embed_origins', '' ) ) . '</textarea>', 'recplanet', 'rp_embed' );
	}

	private static function text( string $name, string $type = 'text' ): void {
		printf( '<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off">', esc_attr( $type ), esc_attr( $name ), esc_attr( get_option( $name, '' ) ) );
	}

	public static function render(): void {
		$drift = get_option( 'rp_counter_drift' );
		echo '<div class="wrap"><h1>RecPlanet settings</h1>';
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

	public static function geocoding_key(): string {
		return trim( (string) get_option( 'rp_geocoding_key', '' ) );
	}

	public static function vote_message(): string {
		return trim( (string) get_option( 'rp_vote_message', self::DEFAULT_VOTE_MESSAGE ) );
	}

	public static function facebook_url(): string {
		return (string) get_option( 'rp_facebook_url', self::DEFAULT_FACEBOOK ) ?: self::DEFAULT_FACEBOOK;
	}

	/** The ad code saved for a slot, or an empty string. */
	public static function ad( string $slot ): string {
		return isset( self::AD_SLOTS[ $slot ] ) ? trim( (string) get_option( "rp_ad_$slot", '' ) ) : '';
	}

	/** The loader script, only when at least one slot has code; printed once in the head. */
	public static function ads_loader(): string {
		$loader = trim( (string) get_option( 'rp_ads_loader', '' ) );
		if ( '' === $loader ) {
			return '';
		}
		foreach ( array_keys( self::AD_SLOTS ) as $slot ) {
			if ( '' !== self::ad( $slot ) ) {
				return $loader . "\n";
			}
		}
		return '';
	}
}
