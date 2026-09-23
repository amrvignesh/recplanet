<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Star ratings on photos, as on the old site: one to five stars, open to anyone.
 * A visitor without an account is keyed on a salted hash of IP and user agent and can change
 * their rating; a member's rating is tied to the account. Rating is closed once the photo's
 * contest has closed voting.
 *
 * Cached on the photo: rp_votes (number of ratings) and rp_score (average stars, one decimal).
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

	public static function voter_hash(): string {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
		$ip = trim( explode( ',', $ip )[0] );
		return sha1( wp_salt( 'nonce' ) . '|' . $ip . '|' . ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
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
		$uid  = get_current_user_id();
		$hash = $uid ? '' : self::voter_hash();
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO " . table( 'votes' ) . " (photo_id, user_id, voter_hash, value, created_at) VALUES (%d, %d, %s, %d, %s)
			 ON DUPLICATE KEY UPDATE value = VALUES(value), created_at = VALUES(created_at)",
			$id, $uid, $hash, $stars, current_time( 'mysql', true )
		) );
		return new \WP_REST_Response( self::refresh( $id ) + [ 'yours' => $stars ] );
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
		return new \WP_REST_Response( self::refresh( $id ) + [ 'yours' => 0 ] );
	}

	public static function status( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id   = (int) $r['id'];
		$uid  = get_current_user_id();
		$mine = (int) $wpdb->get_var( $uid
			? $wpdb->prepare( "SELECT value FROM " . table( 'votes' ) . " WHERE photo_id = %d AND user_id = %d", $id, $uid )
			: $wpdb->prepare( "SELECT value FROM " . table( 'votes' ) . " WHERE photo_id = %d AND user_id = 0 AND voter_hash = %s", $id, self::voter_hash() ) );
		return new \WP_REST_Response( self::totals( $id ) + [ 'yours' => $mine ] );
	}

	/** Ratings and average stars, live plus the frozen Drupal figures for migrated photos. */
	public static function totals( int $photo_id ): array {
		global $wpdb;
		$live = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(AVG(value),0) AS avg FROM " . table( 'votes' ) . " WHERE photo_id = %d", $photo_id ) );
		$ln   = (int) get_post_meta( $photo_id, 'rp_legacy_votes', true );
		$ls   = (float) get_post_meta( $photo_id, 'rp_legacy_score', true );
		$n    = (int) $live->n + $ln;
		$sum  = (float) $live->n * (float) $live->avg + $ln * $ls;
		return [ 'votes' => $n, 'score' => $n ? round( $sum / $n, 1 ) : 0 ];
	}

	private static function refresh( int $photo_id ): array {
		$t = self::totals( $photo_id );
		update_post_meta( $photo_id, 'rp_votes', $t['votes'] );
		update_post_meta( $photo_id, 'rp_score', $t['score'] );
		return $t;
	}
}
