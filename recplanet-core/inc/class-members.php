<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Members join and sign in on the site's own pages (/join/, /sign-in/) and never see wp-admin.
 * Editors and administrators keep the dashboard. Lost-password and logout stay on core's screens.
 */
class Members {

	public static function init(): void {
		add_filter( 'register_url', fn() => home_url( '/join/' ) );
		add_filter( 'login_url', [ __CLASS__, 'login_url' ], 10, 3 );
		add_action( 'admin_init', [ __CLASS__, 'keep_out' ] );
		add_filter( 'show_admin_bar', fn( $show ) => self::is_editor() ? $show : false );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_filter( 'login_redirect', [ __CLASS__, 'after_login' ], 10, 3 );
		add_filter( 'registration_redirect', fn() => home_url( '/contest/' ) );
	}

	public static function is_editor(): bool {
		return current_user_can( 'edit_rp_parks' ) || current_user_can( 'edit_others_rp_photos' ) || current_user_can( 'edit_posts' );
	}

	/** Core's login link becomes the site's sign-in page; the lost-password and logout actions stay where they are. */
	public static function login_url( string $url, string $redirect, bool $force_reauth ): string {
		if ( str_contains( $url, 'action=' ) ) {
			return $url;
		}
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), home_url( '/sign-in/' ) ) : home_url( '/sign-in/' );
	}

	/** A member who lands in wp-admin is sent to the contest page; form handlers and ajax still work. */
	public static function keep_out(): void {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! is_user_logged_in() || self::is_editor() ) {
			return;
		}
		$script = basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) );
		if ( in_array( $script, [ 'admin-post.php', 'admin-ajax.php', 'async-upload.php' ], true ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/contest/' ) );
		exit;
	}

	public static function after_login( string $to, string $requested, $user ): string {
		if ( $user instanceof \WP_User && ! user_can( $user, 'edit_rp_parks' ) && ! user_can( $user, 'edit_posts' ) && ( '' === $requested || str_contains( $requested, 'wp-admin' ) ) ) {
			return home_url( '/contest/' );
		}
		return $to;
	}

	// ------------------------------------------------------------------ REST

	public static function routes(): void {
		register_rest_route( 'recplanet/v1', '/join', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'join' ], 'permission_callback' => '__return_true' ] );
		register_rest_route( 'recplanet/v1', '/signin', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'signin' ], 'permission_callback' => '__return_true' ] );
	}

	private static function too_many( string $what, int $limit ): bool {
		$key = 'rp_' . $what . '_rl_' . Votes::voter_hash();
		$n   = (int) get_transient( $key );
		set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS );
		return $n >= $limit;
	}

	private static function captcha_ok( \WP_REST_Request $r ): bool|string {
		$secret = get_option( 'rp_turnstile_secret', '' );
		if ( $secret ) {
			$resp = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [ 'timeout' => 8, 'body' => [ 'secret' => $secret, 'response' => (string) $r['turnstile'], 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '' ] ] );
			return ( ! is_wp_error( $resp ) && ! empty( json_decode( wp_remote_retrieve_body( $resp ), true )['success'] ) ) ?: 'The anti-spam check did not pass. Try again.';
		}
		[ $exp, $sig ] = array_pad( explode( '.', (string) $r['token'], 2 ), 2, '' );
		$answer = (int) preg_replace( '/\D/', '', (string) $r['answer'] );
		if ( (int) $exp < time() ) {
			return 'That form sat open too long. Reload the page and try again.';
		}
		if ( ! hash_equals( hash_hmac( 'sha256', $answer . '|' . $exp, wp_salt( 'auth' ) ), $sig ) ) {
			return 'The answer to the sum was not right.';
		}
		return true;
	}

	public static function join( \WP_REST_Request $r ): \WP_REST_Response {
		if ( ! get_option( 'users_can_register' ) ) {
			return new \WP_REST_Response( [ 'error' => 'Registration is closed at the moment.' ], 403 );
		}
		if ( ! empty( $r['website'] ) ) {
			return new \WP_REST_Response( [ 'ok' => true ] );        // honeypot
		}
		if ( self::too_many( 'join', 5 ) ) {
			return new \WP_REST_Response( [ 'error' => 'Too many attempts from this connection. Try again in a few minutes.' ], 429 );
		}
		$name  = sanitize_text_field( mb_substr( (string) $r['name'], 0, 60 ) );
		$email = sanitize_email( (string) $r['email'] );
		$pass  = (string) $r['password'];
		if ( '' === $name || ! is_email( $email ) ) {
			return new \WP_REST_Response( [ 'error' => 'A name and a working email are needed.' ], 400 );
		}
		if ( strlen( $pass ) < 8 ) {
			return new \WP_REST_Response( [ 'error' => 'Choose a password of at least eight characters.' ], 400 );
		}
		$cap = self::captcha_ok( $r );
		if ( true !== $cap ) {
			return new \WP_REST_Response( [ 'error' => $cap ], 400 );
		}
		if ( email_exists( $email ) ) {
			return new \WP_REST_Response( [ 'error' => 'That email already has an account. Sign in instead.' ], 409 );
		}
		$base  = sanitize_user( strtolower( preg_replace( '/[^a-z0-9]+/i', '', $name ) ) ?: strtok( $email, '@' ), true ) ?: 'member';
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}
		$id = wp_insert_user( [ 'user_login' => $login, 'user_email' => $email, 'user_pass' => $pass, 'display_name' => $name, 'nickname' => $name, 'role' => 'rp_member', 'show_admin_bar_front' => 'false' ] );
		if ( is_wp_error( $id ) ) {
			return new \WP_REST_Response( [ 'error' => $id->get_error_message() ], 400 );
		}
		wp_new_user_notification( $id, null, 'admin' );
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id, true );
		do_action( 'wp_login', $login, get_user_by( 'id', $id ) );
		return new \WP_REST_Response( [ 'ok' => true, 'to' => self::safe_to( (string) $r['redirect_to'] ) ] );
	}

	public static function signin( \WP_REST_Request $r ): \WP_REST_Response {
		if ( self::too_many( 'signin', 8 ) ) {
			return new \WP_REST_Response( [ 'error' => 'Too many attempts from this connection. Try again in a few minutes.' ], 429 );
		}
		$user = wp_signon( [ 'user_login' => sanitize_text_field( (string) $r['login'] ), 'user_password' => (string) $r['password'], 'remember' => ! empty( $r['remember'] ) ], is_ssl() );
		if ( is_wp_error( $user ) ) {
			return new \WP_REST_Response( [ 'error' => 'That email or username and password do not match.' ], 401 );
		}
		wp_set_current_user( $user->ID );
		$to = self::safe_to( (string) $r['redirect_to'] );
		if ( ! user_can( $user, 'edit_rp_parks' ) && ! user_can( $user, 'edit_posts' ) && str_contains( $to, 'wp-admin' ) ) {
			$to = home_url( '/contest/' );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'to' => $to ] );
	}

	private static function safe_to( string $to ): string {
		$to = wp_validate_redirect( $to, '' );
		return '' !== $to ? $to : home_url( '/contest/' );
	}
}
