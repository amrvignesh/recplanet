<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * The contest edit screen: status, dates, prizes and winners in one box; a list with the same at a glance.
 * Status drives the site: open = entries accepted, voting = ratings only, closed = archived with winners.
 */
class Contest_Admin {

	const NONCE = 'rp_contest_details';

	public static function init(): void {
		add_filter( 'use_block_editor_for_post_type', fn( $use, $type ) => POST_CONTEST === $type ? false : $use, 10, 2 );
		add_action( 'add_meta_boxes_' . POST_CONTEST, fn() => add_meta_box( 'rp_contest_details', 'Contest details', [ __CLASS__, 'render' ], POST_CONTEST, 'normal', 'high' ) );
		add_action( 'save_post_' . POST_CONTEST, [ __CLASS__, 'save' ], 10, 2 );
		add_filter( 'manage_' . POST_CONTEST . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . POST_CONTEST . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_filter( 'enter_title_here', fn( $t, $post ) => POST_CONTEST === $post->post_type ? 'Contest name, e.g. Spring 2027 photo contest' : $t, 10, 2 );
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$m = fn( string $k ) => (string) get_post_meta( $post->ID, $k, true );
		$status  = $m( 'rp_status' ) ?: 'draft';
		$winners = json_decode( $m( 'rp_winners' ), true ) ?: [];
		$days    = (int) get_option( 'rp_contest_days', 30 );
		echo '<style>.rp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 18px;margin-top:8px}.rp-grid label{display:block;font-weight:600;margin-bottom:3px}.rp-grid input,.rp-grid select{width:100%}.rp-grid p{margin:4px 0 0;color:#646970;font-size:12px}.rp-wide{grid-column:1/-1}</style>';
		echo '<p>The description above is what entrants read on the contest page. Only one contest is open at a time: the newest one with status Open or Voting.</p><div class="rp-grid">';
		echo '<div><label for="rp_status">Status</label><select id="rp_status" name="rp_status">';
		foreach ( [ 'draft' => 'Draft (not shown)', 'open' => 'Open: entries and ratings', 'voting' => 'Voting: ratings only', 'closed' => 'Closed: archived with winners' ] as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></div>';
		foreach ( [ 'rp_opens_at' => 'Entries open', 'rp_closes_at' => 'Entries close', 'rp_voting_closes_at' => 'Voting closes' ] as $k => $l ) {
			echo '<div><label for="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</label><input type="date" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $m( $k ) ) . '"></div>';
		}
		echo '<div class="rp-wide"><label for="rp_prizes">Prizes</label><input type="text" id="rp_prizes" name="rp_prizes" maxlength="200" value="' . esc_attr( $m( 'rp_prizes' ) ) . '" placeholder="$150 to the top-rated photo, $50 drawn among voters"><p>Shown on the contest page. The default contest length in Settings › RecPlanet is ' . (int) $days . ' days; dates here override it.</p></div>';
		echo '<div class="rp-wide"><label>Winners</label>';
		$entries = get_posts( [ 'post_type' => POST_PHOTO, 'posts_per_page' => 500, 'post_status' => 'publish', 'meta_key' => 'rp_score', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_query' => [ [ 'key' => 'rp_contest_id', 'value' => $post->ID ] ] ] );
		for ( $i = 0; $i < 3; $i++ ) {
			$cur = (int) ( $winners[ $i ] ?? 0 );
			echo '<select name="rp_winners[]" style="width:100%;margin-bottom:6px"><option value="0">' . esc_html( [ 'First place', 'Second place', 'Third place' ][ $i ] ) . ': none yet</option>';
			foreach ( $entries as $e ) {
				echo '<option value="' . (int) $e->ID . '"' . selected( $cur, $e->ID, false ) . '>' . esc_html( get_the_title( $e ) . ' · ★ ' . number_format( (float) get_post_meta( $e->ID, 'rp_score', true ), 1 ) . ' · ' . ( get_post_meta( $e->ID, 'rp_contributor', true ) ?: get_the_author_meta( 'display_name', $e->post_author ) ) ) . '</option>';
			}
			echo '</select>';
		}
		echo '<p>Entries sorted by rating. Set the winners, then set the status to Closed; the Prize draw page picks the voter prize.</p></div></div>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) || ! current_user_can( 'edit_rp_contests' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$status = sanitize_key( $_POST['rp_status'] ?? 'draft' );
		update_post_meta( $post_id, 'rp_status', in_array( $status, [ 'draft', 'open', 'voting', 'closed' ], true ) ? $status : 'draft' );
		foreach ( [ 'rp_opens_at', 'rp_closes_at', 'rp_voting_closes_at' ] as $k ) {
			$v = (string) ( $_POST[ $k ] ?? '' );
			update_post_meta( $post_id, $k, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '' );
		}
		update_post_meta( $post_id, 'rp_prizes', sanitize_text_field( wp_unslash( $_POST['rp_prizes'] ?? '' ) ) );
		$winners = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['rp_winners'] ?? [] ) ) ) ) );
		update_post_meta( $post_id, 'rp_winners', wp_json_encode( $winners ) );
	}

	public static function columns( array $c ): array {
		$out = [];
		foreach ( $c as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['rp_status']  = 'Status';
				$out['rp_dates']   = 'Dates';
				$out['rp_entries'] = 'Entries';
			}
		}
		unset( $out['date'] );
		return $out;
	}

	public static function column( string $col, int $id ): void {
		global $wpdb;
		if ( 'rp_status' === $col ) {
			echo esc_html( ucfirst( (string) get_post_meta( $id, 'rp_status', true ) ?: 'draft' ) );
		} elseif ( 'rp_dates' === $col ) {
			echo esc_html( ( get_post_meta( $id, 'rp_opens_at', true ) ?: '…' ) . ' → ' . ( get_post_meta( $id, 'rp_closes_at', true ) ?: '…' ) );
		} elseif ( 'rp_entries' === $col ) {
			echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp_contest_id' AND meta_value = %d", $id ) );
		}
	}
}
