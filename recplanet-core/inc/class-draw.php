<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * The voter prize draw, as on the old site: a prize goes to a voter, not a photographer.
 * An editor runs the draw by hand from Contests > Prize draw. The winner is one rating row,
 * chosen at random from the chosen contest and period. A signed-in winner is emailed a claim
 * code; an anonymous winner sees the "Congrats" notice with the code on their next visit,
 * matched by the same voter hash the rating was recorded under. Claims are checked against
 * the code shown here.
 */
class Draw {

	public static function init(): void {
		add_action( 'admin_menu', function () {
			add_submenu_page( 'edit.php?post_type=' . POST_CONTEST, 'Prize draw', 'Prize draw', 'edit_rp_contests', 'rp-draw', [ __CLASS__, 'page' ] );
		} );
		add_action( 'admin_post_rp_draw', [ __CLASS__, 'run' ] );
		add_action( 'admin_post_rp_draw_claim', [ __CLASS__, 'mark_claimed' ] );
		add_action( 'rest_api_init', function () {
			register_rest_route( 'recplanet/v1', '/draw/me', [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'me' ], 'permission_callback' => '__return_true' ] );
		} );
	}

	/** Draws on file, newest first. */
	public static function draws(): array {
		return array_reverse( (array) get_option( 'rp_draws', [] ) );
	}

	public static function run(): void {
		global $wpdb;
		check_admin_referer( 'rp_draw' );
		if ( ! current_user_can( 'edit_rp_contests' ) ) {
			wp_die( 'Not allowed.' );
		}
		$contest = (int) ( $_POST['contest'] ?? 0 );
		$from    = sanitize_text_field( $_POST['from'] ?? '' );
		$to      = sanitize_text_field( $_POST['to'] ?? '' );
		$prize   = sanitize_text_field( $_POST['prize'] ?? '' );

		$where = [ '1=1' ];
		$args  = [];
		if ( $contest ) {
			$where[] = "photo_id IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rp_contest_id' AND meta_value = %d)";
			$args[]  = $contest;
		}
		if ( $from ) {
			$where[] = 'created_at >= %s';
			$args[]  = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where[] = 'created_at <= %s';
			$args[]  = $to . ' 23:59:59';
		}
		// Every distinct voter has one ticket, however many photos they rated.
		$sql    = "SELECT user_id, voter_hash, MAX(created_at) AS last_at, COUNT(*) AS ratings FROM " . table( 'votes' ) . " WHERE " . implode( ' AND ', $where ) . " GROUP BY user_id, voter_hash";
		$voters = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql );
		if ( ! $voters ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'rp-draw', 'rp_msg' => 'none' ], admin_url( 'edit.php?post_type=' . POST_CONTEST ) ) );
			exit;
		}
		$w    = $voters[ random_int( 0, count( $voters ) - 1 ) ];
		$code = strtoupper( wp_generate_password( 12, false, false ) );
		$draw = [
			'id'         => wp_generate_uuid4(),
			'contest_id' => $contest,
			'from'       => $from,
			'to'         => $to,
			'prize'      => $prize,
			'pool'       => count( $voters ),
			'user_id'    => (int) $w->user_id,
			'voter_hash' => $w->voter_hash,
			'ratings'    => (int) $w->ratings,
			'code'       => $code,
			'drawn_at'   => current_time( 'mysql', true ),
			'drawn_by'   => get_current_user_id(),
			'claimed_at' => '',
		];
		$all   = (array) get_option( 'rp_draws', [] );
		$all[] = $draw;
		update_option( 'rp_draws', $all, false );

		if ( $draw['user_id'] ) {
			$u = get_user_by( 'id', $draw['user_id'] );
			if ( $u ) {
				wp_mail( $u->user_email, 'You won the RecPlanet voter prize', "Congratulations! You are the winner among all voters" . ( $prize ? " of $prize" : '' ) . ".\n\nYour claim code is $code. Reply to this email or use the contact page and quote the code.\n\n" . home_url( '/' ) );
			}
		}
		wp_safe_redirect( add_query_arg( [ 'page' => 'rp-draw', 'rp_msg' => 'drawn' ], admin_url( 'edit.php?post_type=' . POST_CONTEST ) ) );
		exit;
	}

	public static function mark_claimed(): void {
		check_admin_referer( 'rp_draw_claim' );
		if ( ! current_user_can( 'edit_rp_contests' ) ) {
			wp_die( 'Not allowed.' );
		}
		$id  = sanitize_text_field( $_POST['draw'] ?? '' );
		$all = (array) get_option( 'rp_draws', [] );
		foreach ( $all as &$d ) {
			if ( $d['id'] === $id ) {
				$d['claimed_at'] = $d['claimed_at'] ? '' : current_time( 'mysql', true );
			}
		}
		update_option( 'rp_draws', $all, false );
		wp_safe_redirect( add_query_arg( [ 'page' => 'rp-draw' ], admin_url( 'edit.php?post_type=' . POST_CONTEST ) ) );
		exit;
	}

	/** Is the current visitor an unclaimed winner? The theme shows the Congrats notice if so. */
	public static function me( \WP_REST_Request $r ): \WP_REST_Response {
		$uid  = get_current_user_id();
		$hash = Votes::voter_hash();
		foreach ( self::draws() as $d ) {
			if ( $d['claimed_at'] ) {
				continue;
			}
			if ( ( $uid && $d['user_id'] === $uid ) || ( ! $d['user_id'] && $d['voter_hash'] === $hash ) ) {
				return new \WP_REST_Response( [ 'winner' => true, 'code' => $d['code'], 'prize' => $d['prize'], 'drawn_at' => $d['drawn_at'] ] );
			}
		}
		return new \WP_REST_Response( [ 'winner' => false ] );
	}

	public static function page(): void {
		global $wpdb;
		$contests = get_posts( [ 'post_type' => POST_CONTEST, 'posts_per_page' => 50, 'post_status' => 'any', 'orderby' => 'date', 'order' => 'DESC' ] );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT CONCAT(user_id, '|', voter_hash)) FROM " . table( 'votes' ) );
		$msg      = sanitize_text_field( $_GET['rp_msg'] ?? '' );
		echo '<div class="wrap"><h1>Prize draw</h1>';
		if ( 'drawn' === $msg ) {
			echo '<div class="notice notice-success"><p>Winner drawn. The code is in the list below. A signed-in winner has been emailed; an anonymous winner sees the notice on their next visit.</p></div>';
		} elseif ( 'none' === $msg ) {
			echo '<div class="notice notice-warning"><p>No voters matched that contest and period.</p></div>';
		}
		echo '<p>Every distinct voter, signed in or not, has one ticket. ' . esc_html( number_format( $total ) ) . ' voters on file in total.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="background:#fff;padding:16px;border:1px solid #ccd0d4;max-width:640px">';
		wp_nonce_field( 'rp_draw' );
		echo '<input type="hidden" name="action" value="rp_draw">';
		echo '<table class="form-table"><tr><th><label for="contest">Contest</label></th><td><select name="contest" id="contest"><option value="0">All contests</option>';
		foreach ( $contests as $c ) {
			echo '<option value="' . (int) $c->ID . '">' . esc_html( $c->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="from">Ratings from</label></th><td><input type="date" name="from" id="from"> to <input type="date" name="to" id="to"> <span class="description">leave empty for all time</span></td></tr>';
		echo '<tr><th><label for="prize">Prize</label></th><td><input type="text" name="prize" id="prize" class="regular-text" placeholder="e.g. $150 gift card"></td></tr></table>';
		submit_button( 'Draw a winner' );
		echo '</form>';

		echo '<h2>Draws</h2><table class="widefat striped"><thead><tr><th>Drawn</th><th>Contest</th><th>Period</th><th>Prize</th><th>Pool</th><th>Winner</th><th>Code</th><th>Claimed</th></tr></thead><tbody>';
		foreach ( self::draws() as $d ) {
			$who = $d['user_id'] ? ( ( $u = get_user_by( 'id', $d['user_id'] ) ) ? esc_html( $u->display_name . ' <' . $u->user_email . '>' ) : 'user #' . (int) $d['user_id'] ) : 'anonymous voter (sees the notice on next visit)';
			echo '<tr><td>' . esc_html( $d['drawn_at'] ) . '</td><td>' . esc_html( $d['contest_id'] ? get_the_title( $d['contest_id'] ) : 'All' ) . '</td><td>' . esc_html( trim( $d['from'] . ' – ' . $d['to'], ' –' ) ?: 'all time' ) . '</td><td>' . esc_html( $d['prize'] ) . '</td><td>' . (int) $d['pool'] . '</td><td>' . $who . '<br><small>' . (int) $d['ratings'] . ' ratings</small></td><td><code>' . esc_html( $d['code'] ) . '</code></td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'rp_draw_claim' );
			echo '<input type="hidden" name="action" value="rp_draw_claim"><input type="hidden" name="draw" value="' . esc_attr( $d['id'] ) . '">';
			echo $d['claimed_at'] ? esc_html( $d['claimed_at'] ) . ' <button class="button-link">undo</button>' : '<button class="button">Mark claimed</button>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
