<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * The photo edit screen and list for editors: which park, which contest, who took it and when, the rating,
 * and a pending count on the menu so approvals are never missed. Approving is publishing.
 */
class Photo_Admin {

	const NONCE = 'rp_photo_details';

	public static function init(): void {
		add_filter( 'use_block_editor_for_post_type', fn( $use, $type ) => POST_PHOTO === $type ? false : $use, 10, 2 );
		add_action( 'add_meta_boxes_' . POST_PHOTO, fn() => add_meta_box( 'rp_photo_details', 'Photo details', [ __CLASS__, 'render' ], POST_PHOTO, 'normal', 'high' ) );
		add_action( 'save_post_' . POST_PHOTO, [ __CLASS__, 'save' ], 10, 2 );
		add_filter( 'manage_' . POST_PHOTO . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . POST_PHOTO . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_action( 'admin_menu', [ __CLASS__, 'badge' ], 99 );
		add_filter( 'enter_title_here', fn( $t, $post ) => POST_PHOTO === $post->post_type ? 'Photo title' : $t, 10, 2 );
	}

	public static function pending(): int {
		return (int) ( wp_count_posts( POST_PHOTO )->pending ?? 0 );
	}

	/** "Photos (3)" on the admin menu while entries wait. */
	public static function badge(): void {
		global $menu;
		$n = self::pending();
		if ( ! $n || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . POST_PHOTO === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>';
			}
		}
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$park     = (int) get_post_meta( $post->ID, 'rp_park_id', true );
		$contest  = (int) get_post_meta( $post->ID, 'rp_contest_id', true );
		$contests = get_posts( [ 'post_type' => POST_CONTEST, 'posts_per_page' => 50, 'post_status' => 'any', 'orderby' => 'date', 'order' => 'DESC' ] );
		echo '<style>.rp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 18px;margin-top:8px}.rp-grid label{display:block;font-weight:600;margin-bottom:3px}.rp-grid input,.rp-grid select{width:100%}.rp-grid p{margin:4px 0 0;color:#646970;font-size:12px}</style>';
		if ( 'pending' === $post->post_status ) {
			echo '<div class="notice notice-info inline"><p><strong>Awaiting approval.</strong> Check the photo follows the rules (taken in a park or recreation area, no children, the member&rsquo;s own), then press Publish. Trash it to reject.</p></div>';
		}
		echo '<div class="rp-grid">';
		echo '<div><label for="rp_park_q">Park it shows or was taken at</label><input type="text" id="rp_park_q" list="rp_park_list" autocomplete="off" value="' . esc_attr( $park ? get_the_title( $park ) : '' ) . '" placeholder="Start typing a park name"><datalist id="rp_park_list"></datalist><input type="hidden" name="rp_park_id" id="rp_park_id" value="' . (int) $park . '"><p>Optional. A contest entry can be of anything recreational; a park photo shows on that park&rsquo;s page.</p></div>';
		echo '<div><label for="rp_contest_id">Contest</label><select id="rp_contest_id" name="rp_contest_id"><option value="0">Not entered in a contest</option>';
		foreach ( $contests as $c ) {
			echo '<option value="' . (int) $c->ID . '"' . selected( $contest, $c->ID, false ) . '>' . esc_html( get_the_title( $c ) . ' (' . ( get_post_meta( $c->ID, 'rp_status', true ) ?: 'draft' ) . ')' ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label for="rp_taken_on">Taken on</label><input type="date" id="rp_taken_on" name="rp_taken_on" value="' . esc_attr( (string) get_post_meta( $post->ID, 'rp_taken_on', true ) ) . '"></div>';
		echo '<div><label for="rp_contributor">Photographer, when not a member</label><input type="text" id="rp_contributor" name="rp_contributor" maxlength="80" value="' . esc_attr( (string) get_post_meta( $post->ID, 'rp_contributor', true ) ) . '"><p>Leave empty to credit the author above. The old site&rsquo;s photos carry their original names here.</p></div>';
		echo '<div><label>Rating</label><div>★ ' . esc_html( number_format( (float) get_post_meta( $post->ID, 'rp_score', true ), 1 ) ) . ' from ' . (int) get_post_meta( $post->ID, 'rp_votes', true ) . ' ratings' . ( get_post_meta( $post->ID, 'rp_legacy_votes', true ) ? ' (' . (int) get_post_meta( $post->ID, 'rp_legacy_votes', true ) . ' carried over from the old site)' : '' ) . '</div></div>';
		echo '</div>';
		echo '<script>(function(){var q=document.getElementById("rp_park_q"),l=document.getElementById("rp_park_list"),h=document.getElementById("rp_park_id"),t=null,o={};q.addEventListener("input",function(){h.value=o[q.value]||0;clearTimeout(t);if(q.value.length<2)return;t=setTimeout(function(){fetch("' . esc_url_raw( rest_url( 'recplanet/v1/parks/suggest' ) ) . '?q="+encodeURIComponent(q.value)).then(function(r){return r.json()}).then(function(rows){o={};l.innerHTML=rows.map(function(r){o[r.label]=r.id;return "<option value=\\""+r.label.replace(/"/g,"&quot;")+"\\">"}).join("");h.value=o[q.value]||0;});},200);});})();</script>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) || ! current_user_can( 'edit_others_rp_photos' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$park = (int) ( $_POST['rp_park_id'] ?? 0 );
		update_post_meta( $post_id, 'rp_park_id', $park && POST_PARK === get_post_type( $park ) ? $park : 0 );
		$contest = (int) ( $_POST['rp_contest_id'] ?? 0 );
		update_post_meta( $post_id, 'rp_contest_id', $contest && POST_CONTEST === get_post_type( $contest ) ? $contest : 0 );
		$taken = (string) ( $_POST['rp_taken_on'] ?? '' );
		update_post_meta( $post_id, 'rp_taken_on', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $taken ) ? $taken : '' );
		update_post_meta( $post_id, 'rp_contributor', sanitize_text_field( wp_unslash( $_POST['rp_contributor'] ?? '' ) ) );
		if ( $park ) {
			Index::upsert( $park );      // has_photo on the park's index row
		}
	}

	public static function columns( array $c ): array {
		$out = [ 'cb' => $c['cb'] ?? '', 'rp_thumb' => '', 'title' => 'Photo', 'rp_by' => 'By', 'rp_park' => 'Park', 'rp_contest' => 'Contest', 'rp_rating' => 'Rating', 'date' => 'Date' ];
		return $out;
	}

	public static function column( string $col, int $id ): void {
		switch ( $col ) {
			case 'rp_thumb':
				echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, [ 60, 60 ] ) : '';
				break;
			case 'rp_by':
				echo esc_html( get_post_meta( $id, 'rp_contributor', true ) ?: get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) ) );
				break;
			case 'rp_park':
				$p = (int) get_post_meta( $id, 'rp_park_id', true );
				echo $p ? '<a href="' . esc_url( get_edit_post_link( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>' : '—';
				break;
			case 'rp_contest':
				$c = (int) get_post_meta( $id, 'rp_contest_id', true );
				echo $c ? esc_html( get_the_title( $c ) ) : '—';
				break;
			case 'rp_rating':
				echo '★ ' . esc_html( number_format( (float) get_post_meta( $id, 'rp_score', true ), 1 ) ) . ' <span style="color:#646970">(' . (int) get_post_meta( $id, 'rp_votes', true ) . ')</span>';
				break;
		}
	}
}
