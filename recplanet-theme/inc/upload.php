<?php
/**
 * Member photo upload: a contest entry (any recreational photo; the park is optional) or a photo of one park.
 * Creates a pending rp_photo with EXIF date and, when nothing was chosen, the nearest park from the photo's location.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_rp_upload_photo', 'rp_handle_upload' );
add_action( 'admin_post_nopriv_rp_upload_photo', function () {
	wp_safe_redirect( wp_login_url( home_url( '/contest' ) ) );
	exit;
} );

function rp_handle_upload(): void {
	check_admin_referer( 'rp_upload_photo' );
	$back = home_url( '/contest' );
	if ( ! current_user_can( 'rp_enter_contest' ) && ! current_user_can( 'edit_rp_photos' ) ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'Your account cannot enter photos.', $back ) );
		exit;
	}
	if ( empty( $_FILES['photo']['tmp_name'] ) || empty( $_POST['rights'] ) ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'Choose a photo and confirm you hold the rights.', $back ) );
		exit;
	}
	$type = wp_check_filetype_and_ext( $_FILES['photo']['tmp_name'], $_FILES['photo']['name'] );
	if ( ! in_array( $type['type'], [ 'image/jpeg', 'image/png' ], true ) ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'JPEG or PNG only.', $back ) );
		exit;
	}
	if ( $_FILES['photo']['size'] > 20 * 1024 * 1024 ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'That file is over 20 MB.', $back ) );
		exit;
	}
	// One entry per member per hour, to keep floods out.
	$key = 'rp_upload_rl_' . get_current_user_id();
	if ( (int) get_transient( $key ) >= 5 ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'Five photos an hour is the limit. Try again later.', $back ) );
		exit;
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$kind    = 'park' === ( $_POST['kind'] ?? '' ) ? 'park' : 'contest';
	$title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) ?: 'Untitled';
	$desc    = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
	$tags    = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ) ) ) );
	$park_id = (int) ( $_POST['park_id'] ?? 0 );
	if ( 'park' === $kind && ( ! $park_id || 'rp_park' !== get_post_type( $park_id ) ) ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'That park could not be found.', $back ) );
		exit;
	}
	// A contest entry carries the contest; a park photo only when the member ticked "also enter it".
	$contest_id = 'contest' === $kind ? (int) ( $_POST['contest_id'] ?? 0 ) : (int) ( $_POST['also_contest'] ?? 0 );
	if ( $contest_id && ( 'rp_contest' !== get_post_type( $contest_id ) || ! in_array( get_post_meta( $contest_id, 'rp_status', true ), [ 'open', 'voting' ], true ) ) ) {
		$contest_id = 0;
	}
	if ( $park_id ) {
		$back = add_query_arg( 'park', $park_id, $back );
	}
	if ( ! $park_id && ! empty( $_POST['park_name'] ) ) {
		$found = get_posts( [ 'post_type' => 'rp_park', 's' => sanitize_text_field( wp_unslash( $_POST['park_name'] ) ), 'posts_per_page' => 1, 'post_status' => 'publish' ] );
		$park_id = $found ? $found[0]->ID : 0;
	}
	$taken = sanitize_text_field( wp_unslash( $_POST['taken_on'] ?? '' ) );
	// EXIF: date, and a location to suggest the nearest park if none was chosen.
	if ( 'image/jpeg' === $type['type'] && function_exists( 'exif_read_data' ) ) {
		$exif = @exif_read_data( $_FILES['photo']['tmp_name'] );
		if ( $exif ) {
			if ( ! $taken && ! empty( $exif['DateTimeOriginal'] ) ) {
				$taken = gmdate( 'Y-m-d', strtotime( str_replace( ':', '-', substr( $exif['DateTimeOriginal'], 0, 10 ) ) ) );
			}
			if ( ! $park_id && ! empty( $exif['GPSLatitude'] ) && ! empty( $exif['GPSLongitude'] ) ) {
				$lat = rp_exif_coord( $exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N' );
				$lng = rp_exif_coord( $exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E' );
				$near = rp_index_rows( 'lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f', [ $lat - 0.01, $lat + 0.01, $lng - 0.012, $lng + 0.012 ], "POW(lat-$lat,2)+POW(lng-$lng,2) ASC", 1 );
				if ( $near ) {
					$park_id = (int) $near[0]->post_id;
				}
			}
		}
	}
	$photo_id = wp_insert_post( [
		'post_type'    => 'rp_photo',
		'post_title'   => $title,
		'post_content' => $desc,
		'post_status'  => get_option( 'rp_photo_needs_approval', true ) ? 'pending' : 'publish',
		'post_author'  => get_current_user_id(),
		'meta_input'   => [ 'rp_park_id' => $park_id, 'rp_contest_id' => $contest_id, 'rp_kind' => $kind, 'rp_taken_on' => $taken, 'rp_votes' => 0, 'rp_score' => 0 ],
	], true );
	if ( is_wp_error( $photo_id ) ) {
		wp_safe_redirect( add_query_arg( 'rp_error', 'Could not save the photo. Try again.', $back ) );
		exit;
	}
	$att = media_handle_upload( 'photo', $photo_id );
	if ( is_wp_error( $att ) ) {
		wp_delete_post( $photo_id, true );
		wp_safe_redirect( add_query_arg( 'rp_error', $att->get_error_message(), $back ) );
		exit;
	}
	set_post_thumbnail( $photo_id, $att );
	if ( $tags ) {
		wp_set_post_tags( $photo_id, array_slice( array_values( $tags ), 0, 10 ) );
	}
	wp_safe_redirect( add_query_arg( 'rp_uploaded', '1', $back . '#enter' ) );
	exit;
}

function rp_exif_coord( array $parts, string $ref ): float {
	$v = [];
	foreach ( $parts as $p ) {
		[ $n, $d ] = array_map( 'floatval', explode( '/', $p . '/1' ) );
		$v[] = $d ? $n / $d : 0;
	}
	$deg = ( $v[0] ?? 0 ) + ( $v[1] ?? 0 ) / 60 + ( $v[2] ?? 0 ) / 3600;
	return in_array( $ref, [ 'S', 'W' ], true ) ? -$deg : $deg;
}

/** Park name lookup for the upload form's datalist. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'recplanet/v1', '/parks/suggest', [ 'methods' => 'GET', 'callback' => function ( WP_REST_Request $r ) {
		global $wpdb;
		$q = sanitize_text_field( $r['q'] ?? '' );
		if ( strlen( $q ) < 2 ) {
			return [];
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, title, city, state FROM " . RP\table( 'park_index' ) . " WHERE title LIKE %s ORDER BY title LIMIT 12", $wpdb->esc_like( $q ) . '%' ) );
		return array_map( fn( $p ) => [ 'id' => (int) $p->post_id, 'label' => $p->title . ( $p->city ? ', ' . $p->city : '' ) . ( $p->state ? ' ' . $p->state : '' ) ], $rows );
	}, 'permission_callback' => '__return_true' ] );
} );
