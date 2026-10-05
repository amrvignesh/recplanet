<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * RecPlanet › Votes: who is voting, from where. Every vote is recorded with its IP address; this page shows the
 * busiest addresses and the latest votes so an editor can spot stuffing, remove an address's votes and block it.
 */
class Votes_Admin {

	public static function init(): void {
		add_action( 'admin_menu', fn() => add_submenu_page( 'rp-inbox', 'Votes', 'Votes', 'edit_rp_contests', 'rp-votes', [ __CLASS__, 'page' ] ), 21 );
		add_action( 'admin_post_rp_votes', [ __CLASS__, 'act' ] );
	}

	public static function blocked(): array {
		return array_values( array_filter( (array) get_option( 'rp_blocked_ips', [] ) ) );
	}

	public static function page(): void {
		global $wpdb;
		$t    = table( 'votes' );
		$days = max( 1, min( 3650, (int) ( $_GET['days'] ?? 30 ) ) );
		$one  = fn( $sql ) => (int) $wpdb->get_var( $sql );
		echo '<div class="wrap"><h1>Votes</h1>';
		if ( ! empty( $_GET['done'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ) . '</p></div>';
		}
		echo '<p>Every vote is kept with the IP address it came from. One address can vote once on each photo, whether signed in or not, and votes carried over from the old site count against that too. '
			. number_format( $one( "SELECT COUNT(*) FROM $t" ) ) . ' votes on file from ' . number_format( $one( "SELECT COUNT(DISTINCT ip_key) FROM $t WHERE ip_key <> ''" ) ) . ' addresses; '
			. number_format( $one( "SELECT COUNT(*) FROM $t WHERE legacy = 0 AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)" ) ) . ' new in the last day, '
			. number_format( $one( "SELECT COUNT(*) FROM $t WHERE legacy = 0 AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" ) ) . ' in the last week.</p>';
		$form = fn( string $do, string $ip, string $label, string $class = 'button button-small', string $confirm = '' ) => '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . wp_nonce_field( 'rp_votes', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_votes"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="ip" value="' . esc_attr( $ip ) . '"><button class="' . esc_attr( $class ) . '"' . ( $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>' . esc_html( $label ) . '</button></form>';

		echo '<h2>Busiest addresses, last ' . (int) $days . ' days</h2><p><a href="' . esc_url( admin_url( 'admin.php?page=rp-votes&days=7' ) ) . '">7 days</a> · <a href="' . esc_url( admin_url( 'admin.php?page=rp-votes&days=30' ) ) . '">30 days</a> · <a href="' . esc_url( admin_url( 'admin.php?page=rp-votes&days=3650' ) ) . '">all time, old site included</a></p>';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ip_key, COUNT(*) votes, COUNT(DISTINCT photo_id) photos, COUNT(DISTINCT user_id) accounts, ROUND(AVG(value),1) avg_stars, SUM(legacy) old_votes, MIN(created_at) first_at, MAX(created_at) last_at FROM $t WHERE ip_key <> '' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) GROUP BY ip_key ORDER BY votes DESC LIMIT 40", $days ) );
		$blocked = self::blocked();
		echo '<table class="widefat striped"><thead><tr><th>Address</th><th>Votes</th><th>Photos</th><th>Accounts</th><th>Average</th><th>First</th><th>Last</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$is = in_array( $r->ip_key, $blocked, true );
			echo '<tr><td><code>' . esc_html( $r->ip_key ) . '</code>' . ( $is ? ' <strong style="color:#d63638">blocked</strong>' : '' ) . ( $r->old_votes ? '<br><span class="description">' . (int) $r->old_votes . ' from the old site</span>' : '' ) . '</td><td>' . (int) $r->votes . '</td><td>' . (int) $r->photos . '</td><td>' . (int) $r->accounts . '</td><td>★ ' . esc_html( $r->avg_stars ) . '</td><td>' . esc_html( $r->first_at ) . '</td><td>' . esc_html( $r->last_at ) . '</td><td>'
				. $form( $is ? 'unblock' : 'block', $r->ip_key, $is ? 'Unblock' : 'Block' ) . ' '
				. $form( 'remove', $r->ip_key, 'Remove its new votes', 'button button-small button-link-delete', 'Remove every vote this address has cast on the new site? Votes carried over from the old site stay.' ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="8">No votes in this period.</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2 style="margin-top:28px">Latest votes</h2><table class="widefat striped"><thead><tr><th>When</th><th>Photo</th><th>Stars</th><th>Voter #</th><th>Address</th><th>Member</th></tr></thead><tbody>';
		foreach ( $wpdb->get_results( "SELECT * FROM $t WHERE legacy = 0 ORDER BY id DESC LIMIT 100" ) as $v ) {
			$u = $v->user_id ? get_user_by( 'id', (int) $v->user_id ) : null;
			echo '<tr><td>' . esc_html( $v->created_at ) . '</td><td><a href="' . esc_url( get_permalink( (int) $v->photo_id ) ) . '">' . esc_html( get_the_title( (int) $v->photo_id ) ) . '</a></td><td>' . (int) $v->value . '</td><td>' . ( $v->voter_no ? (int) $v->voter_no : '—' ) . '</td><td><code>' . esc_html( $v->ip ) . '</code></td><td>' . ( $u ? esc_html( $u->display_name ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2 style="margin-top:28px">Blocked addresses</h2>';
		if ( $blocked ) {
			echo '<ul>';
			foreach ( $blocked as $ip ) {
				echo '<li><code>' . esc_html( $ip ) . '</code> ' . $form( 'unblock', $ip, 'Unblock' ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>None.</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'rp_votes', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_votes"><input type="hidden" name="do" value="block"><input type="text" name="ip" placeholder="An IP address" style="width:220px"> <button class="button">Block</button></form></div>';
	}

	public static function act(): void {
		check_admin_referer( 'rp_votes' );
		if ( ! current_user_can( 'edit_rp_contests' ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		$do  = sanitize_key( $_POST['do'] ?? '' );
		$ip  = Votes::ip_key( sanitize_text_field( wp_unslash( $_POST['ip'] ?? '' ) ) );
		$msg = 'Nothing changed.';
		if ( '' !== $ip ) {
			$blocked = self::blocked();
			if ( 'block' === $do ) {
				$blocked[] = $ip;
				update_option( 'rp_blocked_ips', array_values( array_unique( $blocked ) ), false );
				$msg = "$ip is blocked from voting.";
			} elseif ( 'unblock' === $do ) {
				update_option( 'rp_blocked_ips', array_values( array_diff( $blocked, [ $ip ] ) ), false );
				$msg = "$ip can vote again.";
			} elseif ( 'remove' === $do ) {
				$photos = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT photo_id FROM " . table( 'votes' ) . " WHERE ip_key = %s AND legacy = 0", $ip ) );
				$n      = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM " . table( 'votes' ) . " WHERE ip_key = %s AND legacy = 0", $ip ) );
				foreach ( $photos as $p ) {
					Votes::refresh( (int) $p );
				}
				$msg = "$n votes from $ip removed and " . count( $photos ) . ' photos recounted.';
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rp-votes&done=' . rawurlencode( $msg ) ) );
		exit;
	}
}
