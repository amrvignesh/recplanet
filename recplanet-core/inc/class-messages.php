<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Contact messages and corrections, stored in the site and read from the admin.
 *
 * A top-level RecPlanet menu holds the Inbox (contact messages) and Corrections (visitors who
 * tapped "Something changed" on a park), each with an unread badge, plus a dashboard widget.
 * The contact form is protected by a honeypot, a timing check, a rate limit, and a captcha:
 * Cloudflare Turnstile when its keys are set under Settings > RecPlanet, otherwise a signed
 * arithmetic question that needs no outside account.
 */
class Messages {

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_rp_msg', [ __CLASS__, 'act' ] );
		add_action( 'wp_dashboard_setup', function () {
			wp_add_dashboard_widget( 'rp_inbox', 'RecPlanet inbox', [ __CLASS__, 'widget' ] );
		} );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 90 );
	}

	// ------------------------------------------------------------------ counts

	public static function unread( string $kind ): int {
		global $wpdb;
		if ( 'correction' === $kind ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . table( 'freshness' ) . " WHERE answer = 'no' AND read_at IS NULL" );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . table( 'messages' ) . " WHERE read_at IS NULL" );
	}

	private static function badge( int $n ): string {
		return $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>' : '';
	}

	// ------------------------------------------------------------------ captcha

	/** The question and a signed token the form carries; the answer is checked against the token. */
	public static function challenge(): array {
		$a = random_int( 2, 9 );
		$b = random_int( 2, 9 );
		$exp = time() + 2 * HOUR_IN_SECONDS;
		$sig = hash_hmac( 'sha256', ( $a + $b ) . '|' . $exp, wp_salt( 'auth' ) );
		return [ 'question' => "What is $a plus $b?", 'token' => $exp . '.' . $sig ];
	}

	private static function check_captcha( \WP_REST_Request $r ): bool|string {
		$secret = get_option( 'rp_turnstile_secret', '' );
		if ( $secret ) {
			$resp = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [ 'timeout' => 8, 'body' => [ 'secret' => $secret, 'response' => (string) $r['turnstile'], 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '' ] ] );
			$ok   = ! is_wp_error( $resp ) && ! empty( json_decode( wp_remote_retrieve_body( $resp ), true )['success'] );
			return $ok ?: 'The anti-spam check did not pass. Try again.';
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

	// ------------------------------------------------------------------ REST

	public static function routes(): void {
		register_rest_route( 'recplanet/v1', '/contact/challenge', [ 'methods' => 'GET', 'callback' => fn() => new \WP_REST_Response( self::challenge() ), 'permission_callback' => '__return_true' ] );
		register_rest_route( 'recplanet/v1', '/contact', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'submit' ], 'permission_callback' => '__return_true' ] );
	}

	public static function submit( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		if ( ! empty( $r['website'] ) ) {                       // honeypot: bots fill it, people never see it
			return new \WP_REST_Response( [ 'ok' => true ] );
		}
		$started = (int) $r['started'];
		if ( $started && time() - $started < 4 ) {              // nobody writes a message in three seconds
			return new \WP_REST_Response( [ 'error' => 'That was quick. Take a moment and send again.' ], 400 );
		}
		$hash = Votes::voter_hash();
		$key  = 'rp_contact_rl_' . $hash;
		if ( (int) get_transient( $key ) >= 5 ) {
			return new \WP_REST_Response( [ 'error' => 'Five messages an hour is the limit from one connection.' ], 429 );
		}
		$name    = sanitize_text_field( mb_substr( (string) $r['name'], 0, 120 ) );
		$email   = sanitize_email( (string) $r['email'] );
		$subject = sanitize_text_field( mb_substr( (string) $r['subject'], 0, 120 ) );
		$message = sanitize_textarea_field( mb_substr( (string) $r['message'], 0, 5000 ) );
		$park    = (int) $r['park_id'];
		if ( '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 10 ) {
			return new \WP_REST_Response( [ 'error' => 'A name, a working email and a message of a few words are needed.' ], 400 );
		}
		$cap = self::check_captcha( $r );
		if ( true !== $cap ) {
			return new \WP_REST_Response( [ 'error' => $cap ], 400 );
		}
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
		$wpdb->insert( table( 'messages' ), [
			'name' => $name, 'email' => $email, 'subject' => $subject ?: 'Message', 'message' => $message, 'park_id' => $park,
			'user_id' => get_current_user_id(), 'voter_hash' => $hash, 'created_at' => current_time( 'mysql', true ),
		] );
		$to = get_option( 'rp_contact_email', '' ) ?: get_option( 'admin_email' );
		wp_mail( $to, '[RecPlanet] ' . ( $subject ?: 'Message' ) . ' from ' . $name, "$message\n\nFrom: $name <$email>" . ( $park ? "\nAbout: " . get_the_title( $park ) . ' ' . get_permalink( $park ) : '' ) . "\n\nRead and reply in the site's Inbox: " . admin_url( 'admin.php?page=rp-inbox' ), [ 'Reply-To: ' . $name . ' <' . $email . '>' ] );
		return new \WP_REST_Response( [ 'ok' => true ] );
	}

	// ------------------------------------------------------------------ admin

	public static function menu(): void {
		$m = self::unread( 'message' );
		$c = self::unread( 'correction' );
		add_menu_page( 'RecPlanet inbox', 'RecPlanet' . self::badge( $m + $c ), 'edit_rp_parks', 'rp-inbox', [ __CLASS__, 'inbox' ], 'dashicons-palmtree', 3 );
		add_submenu_page( 'rp-inbox', 'Inbox', 'Inbox' . self::badge( $m ), 'edit_rp_parks', 'rp-inbox', [ __CLASS__, 'inbox' ] );
		add_submenu_page( 'rp-inbox', 'Corrections', 'Corrections' . self::badge( $c ), 'edit_rp_parks', 'rp-corrections', [ __CLASS__, 'corrections' ] );
		add_submenu_page( 'rp-inbox', 'Prize draw', 'Prize draw', 'edit_rp_contests', 'rp-draw-link', function () { wp_safe_redirect( admin_url( 'edit.php?post_type=rp_contest&page=rp-draw' ) ); exit; } );
		add_submenu_page( 'rp-inbox', 'Settings', 'Settings', 'manage_options', 'rp-settings-link', function () { wp_safe_redirect( admin_url( 'options-general.php?page=recplanet' ) ); exit; } );
	}

	public static function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'edit_rp_parks' ) ) {
			return;
		}
		$n = self::unread( 'message' ) + self::unread( 'correction' );
		if ( $n ) {
			$bar->add_node( [ 'id' => 'rp-inbox', 'title' => '<span class="ab-icon dashicons dashicons-email-alt"></span><span class="ab-label">' . $n . '</span>', 'href' => admin_url( 'admin.php?page=rp-inbox' ), 'meta' => [ 'title' => "$n unread in the RecPlanet inbox" ] ] );
		}
	}

	public static function widget(): void {
		$m = self::unread( 'message' );
		$c = self::unread( 'correction' );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=rp-inbox' ) ) . '"><strong>' . (int) $m . '</strong> unread ' . ( 1 === $m ? 'message' : 'messages' ) . '</a> &nbsp;·&nbsp; <a href="' . esc_url( admin_url( 'admin.php?page=rp-corrections' ) ) . '"><strong>' . (int) $c . '</strong> unread ' . ( 1 === $c ? 'correction' : 'corrections' ) . '</a></p>';
		global $wpdb;
		$latest = $wpdb->get_results( "SELECT name, subject, created_at FROM " . table( 'messages' ) . " ORDER BY id DESC LIMIT 5" );
		if ( $latest ) {
			echo '<ul>';
			foreach ( $latest as $l ) {
				echo '<li>' . esc_html( $l->created_at ) . ' · <strong>' . esc_html( $l->name ) . '</strong>: ' . esc_html( $l->subject ) . '</li>';
			}
			echo '</ul>';
		}
	}

	public static function act(): void {
		check_admin_referer( 'rp_msg' );
		if ( ! current_user_can( 'edit_rp_parks' ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		$kind = sanitize_key( $_POST['kind'] ?? 'message' );
		$id   = (int) ( $_POST['id'] ?? 0 );
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$t    = 'correction' === $kind ? table( 'freshness' ) : table( 'messages' );
		if ( 'read' === $do ) {
			$wpdb->update( $t, [ 'read_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
		} elseif ( 'unread' === $do ) {
			$wpdb->update( $t, [ 'read_at' => null ], [ 'id' => $id ] );
		} elseif ( 'delete' === $do ) {
			$wpdb->delete( $t, [ 'id' => $id ] );
		} elseif ( 'readall' === $do ) {
			$wpdb->query( "UPDATE $t SET read_at = '" . current_time( 'mysql', true ) . "' WHERE read_at IS NULL" . ( 'correction' === $kind ? " AND answer = 'no'" : '' ) );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=rp-inbox' ) );
		exit;
	}

	private static function buttons( string $kind, int $id, bool $read ): string {
		$f = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . wp_nonce_field( 'rp_msg', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_msg"><input type="hidden" name="kind" value="' . esc_attr( $kind ) . '"><input type="hidden" name="id" value="' . $id . '">';
		return $f . '<button class="button button-small" name="do" value="' . ( $read ? 'unread' : 'read' ) . '">' . ( $read ? 'Mark unread' : 'Mark read' ) . '</button> <button class="button-link-delete" name="do" value="delete" onclick="return confirm(\'Delete this?\')">Delete</button></form>';
	}

	public static function inbox(): void {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM " . table( 'messages' ) . " ORDER BY (read_at IS NULL) DESC, id DESC LIMIT 300" );
		echo '<div class="wrap"><h1>Inbox <span class="title-count">' . (int) self::unread( 'message' ) . ' unread</span></h1>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'rp_msg', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_msg"><input type="hidden" name="kind" value="message"><button class="button" name="do" value="readall">Mark all read</button></form><br>';
		echo '<table class="widefat striped"><thead><tr><th style="width:150px">When</th><th>From</th><th>Subject</th><th>Message</th><th style="width:170px"></th></tr></thead><tbody>';
		foreach ( $rows as $m ) {
			$unread = null === $m->read_at;
			echo '<tr' . ( $unread ? ' style="font-weight:600;background:#fff8e5"' : '' ) . '><td>' . esc_html( $m->created_at ) . ( $unread ? '<br><span class="dashicons dashicons-marker" style="color:#E85305"></span> new' : '' ) . '</td>';
			echo '<td>' . esc_html( $m->name ) . '<br><a href="mailto:' . esc_attr( $m->email ) . '?subject=' . rawurlencode( 'Re: ' . $m->subject ) . '">' . esc_html( $m->email ) . '</a></td>';
			echo '<td>' . esc_html( $m->subject ) . ( $m->park_id ? '<br><a href="' . esc_url( get_edit_post_link( $m->park_id ) ) . '">' . esc_html( get_the_title( $m->park_id ) ) . '</a>' : '' ) . '</td>';
			echo '<td style="white-space:pre-wrap;font-weight:400">' . esc_html( $m->message ) . '</td><td>' . self::buttons( 'message', (int) $m->id, ! $unread ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No messages yet.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function corrections(): void {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT f.*, p.post_title FROM " . table( 'freshness' ) . " f JOIN {$wpdb->posts} p ON p.ID = f.park_id WHERE f.answer = 'no' ORDER BY (f.read_at IS NULL) DESC, f.id DESC LIMIT 300" );
		$yes  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . table( 'freshness' ) . " WHERE answer = 'yes' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)" );
		echo '<div class="wrap"><h1>Corrections <span class="title-count">' . (int) self::unread( 'correction' ) . ' unread</span></h1>';
		echo '<p>Visitors who tapped "Something changed" on a park. In the last 30 days, ' . $yes . ' visitors confirmed a park as still right.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'rp_msg', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_msg"><input type="hidden" name="kind" value="correction"><button class="button" name="do" value="readall">Mark all read</button></form><br>';
		echo '<table class="widefat striped"><thead><tr><th style="width:150px">When</th><th>Park</th><th>What changed</th><th style="width:170px"></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$unread = null === $r->read_at;
			echo '<tr' . ( $unread ? ' style="font-weight:600;background:#fff8e5"' : '' ) . '><td>' . esc_html( $r->created_at ) . ( $unread ? '<br><span class="dashicons dashicons-marker" style="color:#E85305"></span> new' : '' ) . '</td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $r->park_id ) ) . '">' . esc_html( $r->post_title ) . '</a><br><a href="' . esc_url( get_permalink( $r->park_id ) ) . '" target="_blank" style="font-weight:400">view</a></td>';
			echo '<td style="white-space:pre-wrap;font-weight:400">' . esc_html( $r->note ?: '(no note)' ) . '</td><td>' . self::buttons( 'correction', (int) $r->id, ! $unread ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="4">No corrections yet.</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
