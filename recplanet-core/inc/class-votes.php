<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Voting on photos. As on the old site, no account is needed: one vote per photo per visitor,
 * keyed on a salted hash of IP and user agent. Members' votes are tied to the account and can be
 * changed until the contest's voting closes.
 */
class Votes {

	public static function init(): void {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'recplanet/v1', '/photos/(?P<id>\d+)/vote', [
				[ 'methods' => 'POST', 'callback' => [ __CLASS__, 'vote' ], 'permission_callback' => '__return_true' ],
				[ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'unvote' ], 'permission_callback' => '__return_true' ],
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
			return 'That photo is not open for voting.';
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
		if ( $n >= 60 ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	public static function vote( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id = (int) $r['id'];
		$ok = self::open( $id );
		if ( true !== $ok ) {
			return new \WP_REST_Response( [ 'error' => $ok ], 403 );
		}
		if ( self::rate_limited() ) {
			return new \WP_REST_Response( [ 'error' => 'Too many votes from this connection. Try again later.' ], 429 );
		}
		$uid  = get_current_user_id();
		$hash = $uid ? '' : self::voter_hash();
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO " . table( 'votes' ) . " (photo_id, user_id, voter_hash, value, created_at) VALUES (%d, %d, %s, 1, %s)", $id, $uid, $hash, current_time( 'mysql', true ) ) );
		return new \WP_REST_Response( self::refresh( $id ) + [ 'voted' => true ] );
	}

	public static function unvote( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id  = (int) $r['id'];
		$ok  = self::open( $id );
		if ( true !== $ok ) {
			return new \WP_REST_Response( [ 'error' => $ok ], 403 );
		}
		$uid = get_current_user_id();
		$wpdb->delete( table( 'votes' ), $uid ? [ 'photo_id' => $id, 'user_id' => $uid ] : [ 'photo_id' => $id, 'user_id' => 0, 'voter_hash' => self::voter_hash() ] );
		return new \WP_REST_Response( self::refresh( $id ) + [ 'voted' => false ] );
	}

	public static function status( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id  = (int) $r['id'];
		$uid = get_current_user_id();
		$has = (int) $wpdb->get_var( $uid
			? $wpdb->prepare( "SELECT COUNT(*) FROM " . table( 'votes' ) . " WHERE photo_id = %d AND user_id = %d", $id, $uid )
			: $wpdb->prepare( "SELECT COUNT(*) FROM " . table( 'votes' ) . " WHERE photo_id = %d AND user_id = 0 AND voter_hash = %s", $id, self::voter_hash() ) );
		return new \WP_REST_Response( [ 'votes' => self::count( $id ), 'voted' => $has > 0 ] );
	}

	/** Live votes plus the frozen Drupal total for migrated photos. */
	public static function count( int $photo_id ): int {
		global $wpdb;
		$live = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . table( 'votes' ) . " WHERE photo_id = %d", $photo_id ) );
		return $live + (int) get_post_meta( $photo_id, 'rp_legacy_votes', true );
	}

	private static function refresh( int $photo_id ): array {
		$n = self::count( $photo_id );
		update_post_meta( $photo_id, 'rp_votes', $n );
		return [ 'votes' => $n ];
	}
}
