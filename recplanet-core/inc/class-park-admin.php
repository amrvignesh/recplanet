<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * The park edit screen for editors: a "Park details" box with the fields the database needs, required ones marked
 * and enforced, the place terms set from the fields on save, and the index row kept in step (see Index).
 * Parks use the classic editor: the description is plain paragraphs, and required inputs block a half-filled save.
 */
class Park_Admin {

	const NONCE = 'rp_park_details';

	/** Field => [label, type, required, help]. Country, state and city are the location the old database required. */
	private static function fields(): array {
		return [
			'rp_country'     => [ 'Country', 'country', true, 'Two-letter code. Almost everything is in the United States.' ],
			'rp_state'       => [ 'State', 'state', true, 'Required for the United States; a province or region code elsewhere.' ],
			'rp_county'      => [ 'County', 'text', false, 'Optional. "Dane County", "Orleans Parish".' ],
			'rp_city'        => [ 'City', 'text', true, 'The city or town the park is listed under. Its page is /state/city/.' ],
			'rp_street'      => [ 'Street address', 'text', false, '' ],
			'rp_postal'      => [ 'Postal code', 'text', false, '' ],
			'rp_acreage'     => [ 'Acreage', 'acres', false, 'Feeds the acre counter the moment the park is published. Leave empty when unknown.' ],
			'rp_lat'         => [ 'Latitude', 'coord', false, 'Decimal degrees, for the map and Near Me. Both or neither.' ],
			'rp_lng'         => [ 'Longitude', 'coord', false, '' ],
			'rp_website'     => [ 'Website', 'url', false, 'The park&rsquo;s own page, if it has one.' ],
			'rp_verified_on' => [ 'Last verified', 'date', false, 'When the details were last checked against the source.' ],
		];
	}

	public static function init(): void {
		add_filter( 'use_block_editor_for_post_type', fn( $use, $type ) => POST_PARK === $type ? false : $use, 10, 2 );
		add_action( 'add_meta_boxes_' . POST_PARK, [ __CLASS__, 'boxes' ] );
		add_action( 'save_post_' . POST_PARK, [ __CLASS__, 'save' ], 10, 2 );
		add_filter( 'redirect_post_location', [ __CLASS__, 'redirect' ], 10, 2 );
		add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
		add_filter( 'enter_title_here', fn( $t, $post ) => POST_PARK === $post->post_type ? 'Park name (required)' : $t, 10, 2 );
	}

	public static function boxes(): void {
		add_meta_box( 'rp_park_details', 'Park details', [ __CLASS__, 'render' ], POST_PARK, 'normal', 'high' );
		remove_meta_box( TAX_PLACE . 'div', POST_PARK, 'side' );     // set from the fields above, never by hand
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$v = [];
		foreach ( array_keys( self::fields() ) as $k ) {
			$v[ $k ] = (string) get_post_meta( $post->ID, $k, true );
		}
		$v['rp_country'] = $v['rp_country'] ?: 'us';
		$countries = self::countries();
		$states    = us_states();
		echo '<style>.rp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 18px;margin-top:8px}.rp-grid label{display:block;font-weight:600;margin-bottom:3px}.rp-grid .req{color:#d63638}.rp-grid input,.rp-grid select{width:100%}.rp-grid p{margin:4px 0 0;color:#646970;font-size:12px}.rp-wide{grid-column:1/-1}</style>';
		echo '<p>Fields marked <span style="color:#d63638">*</span> are required; a park cannot be published without them. Activities, facilities and who manages it are in the boxes to the right; the description is the editor above; the photo is the featured image.</p><div class="rp-grid">';
		foreach ( self::fields() as $key => [ $label, $type, $req, $help ] ) {
			$id   = esc_attr( $key );
			$star = $req ? ' <span class="req" aria-hidden="true">*</span>' : '';
			echo '<div' . ( 'rp_website' === $key ? ' class="rp-wide"' : '' ) . '><label for="' . $id . '">' . esc_html( $label ) . $star . '</label>';
			switch ( $type ) {
				case 'country':
					echo '<select id="' . $id . '" name="' . $id . '" required>';
					foreach ( $countries as $code => $name ) {
						echo '<option value="' . esc_attr( $code ) . '"' . selected( $v[ $key ], $code, false ) . '>' . esc_html( $name ) . ' (' . esc_html( strtoupper( $code ) ) . ')</option>';
					}
					echo '</select>';
					break;
				case 'state':
					echo '<select id="rp_state_us" name="rp_state_us" required>';
					echo '<option value="">Choose a state</option>';
					foreach ( $states as $code => $name ) {
						echo '<option value="' . esc_attr( $code ) . '"' . selected( $v[ $key ], $code, false ) . '>' . esc_html( $name ) . ' (' . esc_html( $code ) . ')</option>';
					}
					echo '</select>';
					echo '<input id="rp_state_other" name="rp_state_other" type="text" maxlength="8" placeholder="Province or region code" value="' . esc_attr( 'us' === $v['rp_country'] ? '' : $v[ $key ] ) . '" style="display:none;text-transform:uppercase">';
					break;
				case 'acres':
					echo '<input id="' . $id . '" name="' . $id . '" type="number" step="0.01" min="0" inputmode="decimal" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				case 'coord':
					echo '<input id="' . $id . '" name="' . $id . '" type="number" step="any" min="' . ( 'rp_lat' === $key ? '-90' : '-180' ) . '" max="' . ( 'rp_lat' === $key ? '90' : '180' ) . '" inputmode="decimal" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				case 'url':
					echo '<input id="' . $id . '" name="' . $id . '" type="url" placeholder="https://" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				case 'date':
					echo '<input id="' . $id . '" name="' . $id . '" type="date" value="' . esc_attr( $v[ $key ] ) . '">';
					break;
				default:
					echo '<input id="' . $id . '" name="' . $id . '" type="text" maxlength="120" value="' . esc_attr( $v[ $key ] ) . '"' . ( $req ? ' required' : '' ) . '>';
			}
			if ( $help ) {
				echo '<p>' . wp_kses( $help, [ 'a' => [ 'href' => [] ] ] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '<script>(function(){var c=document.getElementById("rp_country"),us=document.getElementById("rp_state_us"),o=document.getElementById("rp_state_other");function t(){var isUs=c.value==="us";us.style.display=isUs?"":"none";us.required=isUs;o.style.display=isUs?"none":"";}c.addEventListener("change",t);t();})();</script>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) || ! current_user_can( 'edit_rp_parks' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$country = strtolower( sanitize_key( $_POST['rp_country'] ?? 'us' ) ) ?: 'us';
		$state   = strtoupper( sanitize_text_field( wp_unslash( 'us' === $country ? ( $_POST['rp_state_us'] ?? '' ) : ( $_POST['rp_state_other'] ?? '' ) ) ) );
		if ( 'us' === $country && ! isset( us_states()[ $state ] ) ) {
			$state = '';
		}
		$meta = [
			'rp_country'     => $country,
			'rp_state'       => $state,
			'rp_county'      => sanitize_text_field( wp_unslash( $_POST['rp_county'] ?? '' ) ),
			'rp_city'        => sanitize_text_field( wp_unslash( $_POST['rp_city'] ?? '' ) ),
			'rp_street'      => sanitize_text_field( wp_unslash( $_POST['rp_street'] ?? '' ) ),
			'rp_postal'      => sanitize_text_field( wp_unslash( $_POST['rp_postal'] ?? '' ) ),
			'rp_acreage'     => '' === trim( (string) ( $_POST['rp_acreage'] ?? '' ) ) ? '' : round( max( 0, (float) $_POST['rp_acreage'] ), 2 ),
			'rp_lat'         => '' === trim( (string) ( $_POST['rp_lat'] ?? '' ) ) ? '' : (float) $_POST['rp_lat'],
			'rp_lng'         => '' === trim( (string) ( $_POST['rp_lng'] ?? '' ) ) ? '' : (float) $_POST['rp_lng'],
			'rp_website'     => esc_url_raw( wp_unslash( $_POST['rp_website'] ?? '' ) ),
			'rp_verified_on' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_POST['rp_verified_on'] ?? '' ) ) ? (string) $_POST['rp_verified_on'] : '',
		];
		if ( '' === $meta['rp_lat'] || '' === $meta['rp_lng'] ) {
			$meta['rp_lat'] = $meta['rp_lng'] = '';       // both or neither
		}
		foreach ( $meta as $k => $val ) {
			update_post_meta( $post_id, $k, $val );
		}
		// The place terms follow the fields.
		$terms = Taxonomies::place_terms( $country, $state, $meta['rp_county'], $meta['rp_city'] );
		if ( $terms ) {
			wp_set_object_terms( $post_id, $terms, TAX_PLACE );
		}
		// Required: a name, a country, a state for the US, a city. Publishing without them is turned into a draft.
		$missing = [];
		if ( '' === trim( $post->post_title ) ) {
			$missing[] = 'a name';
		}
		if ( 'us' === $country && '' === $state ) {
			$missing[] = 'a state';
		}
		if ( '' === $meta['rp_city'] ) {
			$missing[] = 'a city';
		}
		if ( $missing && in_array( $post->post_status, [ 'publish', 'future' ], true ) ) {
			remove_action( 'save_post_' . POST_PARK, [ __CLASS__, 'save' ], 10 );
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
			add_action( 'save_post_' . POST_PARK, [ __CLASS__, 'save' ], 10, 2 );
			set_transient( 'rp_park_missing_' . get_current_user_id(), $missing, 120 );
		}
	}

	public static function redirect( string $location, int $post_id ): string {
		if ( POST_PARK === get_post_type( $post_id ) && get_transient( 'rp_park_missing_' . get_current_user_id() ) ) {
			$location = add_query_arg( 'rp_missing', '1', remove_query_arg( 'message', $location ) );
		}
		return $location;
	}

	public static function notices(): void {
		if ( empty( $_GET['rp_missing'] ) ) {
			return;
		}
		$missing = get_transient( 'rp_park_missing_' . get_current_user_id() );
		delete_transient( 'rp_park_missing_' . get_current_user_id() );
		if ( $missing ) {
			echo '<div class="notice notice-error"><p><strong>Saved as a draft.</strong> A park needs ' . esc_html( implode( ', ', $missing ) ) . ' before it can be published.</p></div>';
		}
	}

	/** Country codes and names from the place terms, United States first. */
	private static function countries(): array {
		$out   = [ 'us' => 'United States' ];
		$terms = get_terms( [ 'taxonomy' => TAX_PLACE, 'hide_empty' => false, 'parent' => 0, 'meta_key' => 'rp_level', 'meta_value' => 'country', 'orderby' => 'name' ] );
		foreach ( is_array( $terms ) ? $terms : [] as $t ) {
			if ( 'us' !== $t->slug ) {
				$out[ $t->slug ] = $t->name;
			}
		}
		return $out;
	}
}
