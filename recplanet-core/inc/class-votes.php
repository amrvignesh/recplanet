<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Star ratings on photos, as on the old site: one to five stars, open to anyone, one vote per photo per
 * connection. Every vote is kept with its IP address; a connection is that address (the /64 for IPv6), whoever is
 * signed in. Votes carried over from the old site are rows here too (legacy = 1) and count against their address. Rating is closed once the photo's contest has
 * closed voting.
 *
 * Two prizes ride on the ratings, both set on the contest:
 *   - the photo prize goes to the first photo whose stars add up to the contest's target (3,000 on the old site);
 *   - the voting prize goes to the voter whose vote is number N in the contest (10,000 on the old site). Every new
 *     vote in an open contest gets the next voter number; the winner is shown a claim code at once.
 *
 * Cached on the photo: rp_votes (number of ratings), rp_score (average stars, one decimal) and rp_stars (the sum).
 * Migrated photos add their frozen Drupal totals, rp_legacy_votes and rp_legacy_score.
 */
class Votes {

	public static function init(): void {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'recplanet/v1', '/photos/(?P<id>\d+)/rating', [
				[ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rate' ], 'permission_callback' => '__return_true',
					'args' => [ 'stars' => [ 'required' => true ] ] ],
				[ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'unrate' ], 'permission_callback' => '__return_true' ],
				[ 'methods' => 'GET', 'callback' => [ __CLASS__, 'status' ], 'permission_callback' => '__return_true' ],
			] );
		} );
	}

	/**
	 * The visitor's address as the server sees it. REMOTE_ADDR only: a forwarding header is something a visitor can
	 * type, and on this host the platform already puts the true client address in REMOTE_ADDR.
	 */
	public static function client_ip(): string {
		$ip = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** What counts as "one connection": an IPv4 address, or the /64 network of an IPv6 one (a home hands out a whole /64). */
	public static function ip_key( string $ip ): string {
		$ip = trim( $ip );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$bin = inet_pton( $ip );
			return false === $bin ? $ip : inet_ntop( substr( $bin, 0, 8 ) . str_repeat( chr( 0 ), 8 ) ) . '/64';
		}
		return $ip;
	}

	/** One anonymous voter is one connection: the browser plays no part, so switching browsers is not a second vote. */
	public static function voter_hash(): string {
		return sha1( wp_salt( 'nonce' ) . '|' . self::ip_key( self::client_ip() ) );
	}

	/** The contest a photo is entered in, when that contest is still taking votes; 0 otherwise. */
	public static function live_contest( int $photo_id ): int {
		$contest = (int) get_post_meta( $photo_id, 'rp_contest_id', true );
		if ( ! $contest || ! in_array( get_post_meta( $contest, 'rp_status', true ), [ 'open', 'voting' ], true ) ) {
			return 0;
		}
		return $contest;
	}

	public static function star_target( int $contest ): int {
		return $contest ? max( 0, (int) get_post_meta( $contest, 'rp_star_target', true ) ) : 0;
	}

	private static function open( int $photo_id ): bool|string {
		if ( get_post_type( $photo_id ) !== POST_PHOTO || get_post_status( $photo_id ) !== 'publish' ) {
			return 'That photo is not open for rating.';
		}
		$contest = (int) get_post_meta( $photo_id, 'rp_contest_id', true );
		if ( $contest ) {
			$closes = get_post_meta( $contest, 'rp_voting_closes_at', true );
			$status = get_post_meta( $contest, 'rp_status', true );
			if ( 'closed' === $status || ( $closes && strtotime( $closes ) < time() ) ) {
				return 'Voting for this contest has closed.';
			}
		}
		return true;
	}

	private static function rate_limited(): bool {
		$key = 'rp_vote_rl_' . self::voter_hash();
		$n   = (int) get_transient( $key );
		if ( $n >= 120 ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	private static function mine( int $photo_id ): ?object {
		global $wpdb;
		$uid = get_current_user_id();
		return $wpdb->get_row( $uid
			? $wpdb->prepare( "SELECT id, value, voter_no FROM " . table( 'votes' ) . " WHERE photo_id = %d AND user_id = %d", $photo_id, $uid )
			: $wpdb->prepare( "SELECT id, value, voter_no FROM " . table( 'votes' ) . " WHERE photo_id = %d AND user_id = 0 AND voter_hash = %s", $photo_id, self::voter_hash() ) );
	}

	public static function rate( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id    = (int) $r['id'];
		$stars = (int) $r['stars'];
		if ( $stars < 1 || $stars > 5 ) {
			return new \WP_REST_Response( [ 'error' => 'Stars must be 1 to 5.' ], 400 );
		}
		$ok = self::open( $id );
		if ( true !== $ok ) {
			return new \WP_REST_Response( [ 'error' => $ok ], 403 );
		}
		if ( self::rate_limited() ) {
			return new \WP_REST_Response( [ 'error' => 'Too many ratings from this connection. Try again later.' ], 429 );
		}
		$uid   = get_current_user_id();
		$hash  = $uid ? '' : self::voter_hash();
		$ip    = self::client_ip();
		$key   = self::ip_key( $ip );
		if ( '' === $key ) {
			return new \WP_REST_Response( [ 'error' => 'Your vote could not be recorded from this connection.' ], 400 );
		}
		if ( in_array( $key, Votes_Admin::blocked(), true ) ) {
			return new \WP_REST_Response( [ 'error' => 'Voting is not available from this connection.' ], 403 );
		}
		$had   = self::mine( $id );
		$extra = [];
		// One vote per connection per photo, as on the old site: a second account, a second browser, or a vote already
		// cast there on the old site all count as the same connection.
		if ( ! $had && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . table( 'votes' ) . " WHERE photo_id = %d AND ip_key = %s LIMIT 1", $id, $key ) ) ) {
			return new \WP_REST_Response( self::totals( $id ) + [ 'error' => 'A vote from your internet connection is already counted for this photo.', 'yours' => 0, 'voter_no' => 0 ], 409 );
		}
		if ( $had ) {
			$wpdb->update( table( 'votes' ), [ 'value' => $stars, 'created_at' => current_time( 'mysql', true ) ], [ 'id' => $had->id ] );
			$no = (int) $had->voter_no;
		} else {
			$contest = self::live_contest( $id );
			$no      = 0;
			// The voter number is handed out under a lock so two votes at the same instant never share one.
			$locked = $contest ? (bool) $wpdb->get_var( "SELECT GET_LOCK('rp_voter_no', 3)" ) : false;
			if ( $contest && 'contest' === get_post_meta( $contest, 'rp_voter_scope', true ) ) {
				// One count across the whole contest.
				$no = 1 + self::voters_so_far( $contest );
			} elseif ( $contest ) {
				// As on the old site: each photo counts its own voters, carrying on from the votes it arrived with.
				$no = 1 + (int) get_post_meta( $id, 'rp_legacy_votes', true ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . table( 'votes' ) . " WHERE photo_id = %d", $id ) );
			}
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO " . table( 'votes' ) . " (photo_id, user_id, voter_hash, value, voter_no, ip, ip_key, created_at) VALUES (%d, %d, %s, %d, %d, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE value = VALUES(value), created_at = VALUES(created_at)",
				$id, $uid, $hash, $stars, $no, $ip, $key, current_time( 'mysql', true )
			) );
			if ( $locked ) {
				$wpdb->query( "SELECT RELEASE_LOCK('rp_voter_no')" );
			}
			if ( $contest && $no && $no === (int) get_post_meta( $contest, 'rp_winning_voter', true ) ) {
				$extra = [ 'winner' => true, 'code' => Draw::auto_win( $contest, $uid, $hash, $no ) ];
			}
		}
		return new \WP_REST_Response( self::refresh( $id ) + [ 'yours' => $stars, 'voter_no' => $no ] + $extra );
	}

	public static function unrate( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id = (int) $r['id'];
		$ok = self::open( $id );
		if ( true !== $ok ) {
			return new \WP_REST_Response( [ 'error' => $ok ], 403 );
		}
		$uid = get_current_user_id();
		$wpdb->delete( table( 'votes' ), $uid ? [ 'photo_id' => $id, 'user_id' => $uid ] : [ 'photo_id' => $id, 'user_id' => 0, 'voter_hash' => self::voter_hash() ] );
		return new \WP_REST_Response( self::refresh( $id ) + [ 'yours' => 0, 'voter_no' => 0 ] );
	}

	public static function status( \WP_REST_Request $r ): \WP_REST_Response {
		$id   = (int) $r['id'];
		$mine = self::mine( $id );
		return new \WP_REST_Response( self::totals( $id ) + [ 'yours' => $mine ? (int) $mine->value : 0, 'voter_no' => $mine ? (int) $mine->voter_no : 0 ] );
	}

	/** Ratings, average stars and the star total, live plus the frozen Drupal figures for migrated photos. */
	public static function totals( int $photo_id ): array {
		global $wpdb;
		$live = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(value),0) AS total FROM " . table( 'votes' ) . " WHERE photo_id = %d", $photo_id ) );
		$ln   = (int) get_post_meta( $photo_id, 'rp_legacy_votes', true );
		$ls   = (float) get_post_meta( $photo_id, 'rp_legacy_score', true );
		$n    = (int) $live->n + $ln;
		$sum  = (float) $live->total + $ln * $ls;
		return [ 'votes' => $n, 'score' => $n ? round( $sum / $n, 1 ) : 0, 'stars' => (int) round( $sum ), 'target' => self::star_target( (int) get_post_meta( $photo_id, 'rp_contest_id', true ) ) ];
	}

	/** Re-cache a photo's figures; called after every vote and by the backfill. Awards the photo prize at the target. */
	public static function refresh( int $photo_id ): array {
		$t = self::totals( $photo_id );
		update_post_meta( $photo_id, 'rp_votes', $t['votes'] );
		update_post_meta( $photo_id, 'rp_score', $t['score'] );
		update_post_meta( $photo_id, 'rp_stars', $t['stars'] );
		$contest = self::live_contest( $photo_id );
		if ( $contest && $t['target'] && $t['stars'] >= $t['target'] && ! get_post_meta( $contest, 'rp_star_winner', true ) ) {
			update_post_meta( $contest, 'rp_star_winner', $photo_id );
			$winners = json_decode( (string) get_post_meta( $contest, 'rp_winners', true ), true ) ?: [];
			if ( ! in_array( $photo_id, $winners, true ) ) {
				array_unshift( $winners, $photo_id );
				update_post_meta( $contest, 'rp_winners', wp_json_encode( array_slice( $winners, 0, 3 ) ) );
			}
			wp_mail( get_option( 'rp_contact_email', '' ) ?: get_option( 'admin_email' ), '[RecPlanet] A photo has reached ' . number_format( $t['target'] ) . ' stars',
				'"' . get_the_title( $photo_id ) . '" is the first photo in "' . get_the_title( $contest ) . '" to reach ' . number_format( $t['target'] ) . " stars and wins the photo contest prize.\n\n" . get_permalink( $photo_id ) . "\n\nIt has been set as the contest's first-place winner: " . admin_url( 'post.php?action=edit&post=' . $contest ) );
		}
		return $t;
	}

	/** Every vote on a contest's photos, old site and new. */
	public static function votes_in_contest( int $contest ): int {
		global $wpdb;
		$live = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . table( 'votes' ) . " v JOIN {$wpdb->postmeta} m ON m.post_id = v.photo_id AND m.meta_key = 'rp_contest_id' AND m.meta_value = %d", $contest ) );
		$old  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CAST(l.meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} l JOIN {$wpdb->postmeta} m ON m.post_id = l.post_id AND m.meta_key = 'rp_contest_id' AND m.meta_value = %d WHERE l.meta_key = 'rp_legacy_votes'", $contest ) );
		return $live + $old;
	}

	/** The most voters any one photo in the contest has: how close the per-photo voting prize is. */
	public static function top_voters( int $contest ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(CAST(v.meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} v JOIN {$wpdb->postmeta} m ON m.post_id = v.post_id AND m.meta_key = 'rp_contest_id' AND m.meta_value = %d WHERE v.meta_key = 'rp_votes'", $contest ) );
	}

	/** Contest-wide counting only: the number the next voter gets, minus one. */
	public static function voters_so_far( int $contest ): int {
		global $wpdb;
		return max( (int) get_post_meta( $contest, 'rp_voter_start', true ), (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(v.voter_no), 0) FROM " . table( 'votes' ) . " v JOIN {$wpdb->postmeta} m ON m.post_id = v.photo_id AND m.meta_key = 'rp_contest_id' AND m.meta_value = %d", $contest ) ) );
	}
}
