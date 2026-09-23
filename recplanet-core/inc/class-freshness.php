<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * "Been here lately? Is this still accurate?" A yes bumps the verified date. A no opens a note
 * that lands in the RecPlanet > Corrections inbox (see Messages). One answer per park per visitor per 30 days.
 */
class Freshness {

	public static function init(): void {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'recplanet/v1', '/parks/(?P<id>\d+)/freshness', [
				'methods' => 'POST', 'callback' => [ __CLASS__, 'answer' ], 'permission_callback' => '__return_true',
				'args' => [ 'answer' => [ 'required' => true ], 'note' => [ 'default' => '' ] ],
			] );
		} );
	}

	public static function answer( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$id     = (int) $r['id'];
		$answer = 'yes' === $r['answer'] ? 'yes' : 'no';
		if ( get_post_type( $id ) !== POST_PARK ) {
			return new \WP_REST_Response( [ 'error' => 'Not a park.' ], 404 );
		}
		$hash = Votes::voter_hash();
		$key  = "rp_fresh_{$id}_" . substr( $hash, 0, 12 );
		if ( get_transient( $key ) ) {
			return new \WP_REST_Response( [ 'ok' => true, 'already' => true ] );
		}
		set_transient( $key, 1, 30 * DAY_IN_SECONDS );
		$wpdb->insert( table( 'freshness' ), [
			'park_id' => $id, 'user_id' => get_current_user_id(), 'voter_hash' => $hash, 'answer' => $answer,
			'note' => sanitize_textarea_field( mb_substr( (string) $r['note'], 0, 2000 ) ), 'created_at' => current_time( 'mysql', true ),
		] );
		if ( 'yes' === $answer ) {
			update_post_meta( $id, 'rp_verified_on', current_time( 'Y-m-d' ) );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'verified_on' => get_post_meta( $id, 'rp_verified_on', true ) ] );
	}

}
