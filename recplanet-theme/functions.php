<?php
/**
 * RecPlanet theme. The companion plugin (recplanet-core) owns the data; this theme draws it.
 */

defined( 'ABSPATH' ) || exit;

define( 'RP_THEME_VERSION', '0.1.0' );
define( 'RP_THEME_DIR', get_template_directory() );
define( 'RP_THEME_URI', get_template_directory_uri() );

require_once RP_THEME_DIR . '/inc/template-tags.php';
require_once RP_THEME_DIR . '/inc/queries.php';
require_once RP_THEME_DIR . '/inc/upload.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'responsive-embeds' );
	add_image_size( 'rp-card', 800, 450, true );
	add_image_size( 'rp-tile', 640, 800, true );
	register_nav_menus( [ 'primary' => 'Primary', 'footer-explore' => 'Footer: explore', 'footer-take-part' => 'Footer: take part', 'footer-about' => 'Footer: about' ] );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'rp-fonts', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700&family=Caveat:wght@600&display=swap', [], null );
	wp_enqueue_style( 'rp-main', RP_THEME_URI . '/assets/css/main.css', [ 'rp-fonts' ], RP_THEME_VERSION );
	wp_enqueue_script( 'rp-main', RP_THEME_URI . '/assets/js/main.js', [], RP_THEME_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
	wp_localize_script( 'rp-main', 'RP', [
		'rest'    => esc_url_raw( rest_url( 'recplanet/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'home'    => home_url( '/' ),
		'isHome'  => is_front_page(),
		'parkId'  => is_singular( 'rp_park' ) ? get_the_ID() : 0,
		'photoId' => is_singular( 'rp_photo' ) ? get_the_ID() : 0,
		'acts'    => RP\ACTIVITIES,
	] );
	if ( is_page_template( 'page-atlas.php' ) ) {
		wp_enqueue_style( 'rp-atlas', RP_THEME_URI . '/assets/css/atlas.css', [ 'rp-main' ], RP_THEME_VERSION );
		wp_enqueue_style( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css', [], '4.7.1' );
		wp_enqueue_script( 'maplibre', 'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js', [], '4.7.1', true );
		wp_enqueue_script( 'rp-atlas', RP_THEME_URI . '/assets/js/atlas.js', [ 'maplibre', 'rp-main' ], RP_THEME_VERSION, true );
	}
	if ( ! is_admin() ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
	}
} );

/** Meta description: the hand-written one from Drupal, else generated from the record. */
add_action( 'wp_head', function () {
	$desc = '';
	if ( is_singular( 'rp_park' ) ) {
		$desc = get_post_meta( get_the_ID(), 'rp_meta_description', true ) ?: rp_park_lead( get_the_ID() );
	} elseif ( is_tax( 'rp_place' ) ) {
		$t = get_queried_object();
		$s = rp_place_stats( $t );
		$desc = sprintf( '%s public places across %s acres in %s, every one checked by a person.', number_format( $s['count'] ), RP\format_acres( $s['acres'], 0 ), $t->name );
	}
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
	}
	echo '<meta name="theme-color" content="#0FB8E6">' . "\n";
}, 5 );

/** Structured data for parks. */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'rp_park' ) ) {
		return;
	}
	$id  = get_the_ID();
	$lat = get_post_meta( $id, 'rp_lat', true );
	$lng = get_post_meta( $id, 'rp_lng', true );
	$ld  = [
		'@context' => 'https://schema.org',
		'@type'    => 'Park',
		'name'     => get_the_title( $id ),
		'url'      => get_permalink( $id ),
		'description' => wp_strip_all_tags( rp_park_lead( $id ) ),
		'address'  => [
			'@type'           => 'PostalAddress',
			'streetAddress'   => get_post_meta( $id, 'rp_street', true ),
			'addressLocality' => get_post_meta( $id, 'rp_city', true ),
			'addressRegion'   => get_post_meta( $id, 'rp_state', true ),
			'postalCode'      => get_post_meta( $id, 'rp_postal', true ),
			'addressCountry'  => strtoupper( get_post_meta( $id, 'rp_country', true ) ?: 'US' ),
		],
	];
	if ( $lat && $lng ) {
		$ld['geo'] = [ '@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng ];
	}
	$acres = get_post_meta( $id, 'rp_acreage', true );
	if ( '' !== $acres ) {
		$ld['additionalProperty'] = [ '@type' => 'PropertyValue', 'name' => 'Area', 'value' => (float) $acres, 'unitText' => 'acres' ];
	}
	if ( has_post_thumbnail( $id ) ) {
		$ld['image'] = get_the_post_thumbnail_url( $id, 'large' );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 6 );

/** The park archive query is set by the plugin's Rewrites; keep other archives sensible. */
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_tax( [ 'rp_activity', 'rp_facility', 'rp_steward' ] ) ) {
		$q->set( 'post_type', 'rp_park' );
		$q->set( 'posts_per_page', 50 );
		$sort = sanitize_key( $_GET['sort'] ?? 'acres' );
		if ( 'name' === $sort ) {
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
		} else {
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'meta_key', 'rp_acreage' );
			$q->set( 'order', 'DESC' );
		}
	}
	if ( $q->is_tax( 'rp_place' ) && 'name' === sanitize_key( $_GET['sort'] ?? '' ) ) {
		$q->set( 'orderby', 'title' );
		$q->set( 'order', 'ASC' );
		$q->set( 'meta_key', '' );
	}
} );

/** Excerpts for parks come from the cleaned body. */
add_filter( 'excerpt_length', fn() => 32 );
add_filter( 'excerpt_more', fn() => '…' );

/** Photo uploads from members go to pending. Contest and park pins come from the form. */
add_filter( 'wp_insert_post_data', function ( array $data ) {
	if ( 'rp_photo' === $data['post_type'] && 'publish' === $data['post_status'] && ! current_user_can( 'edit_others_rp_photos' ) && get_option( 'rp_photo_needs_approval', true ) ) {
		$data['post_status'] = 'pending';
	}
	return $data;
} );
