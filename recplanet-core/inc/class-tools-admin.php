<?php
namespace RP;

defined( 'ABSPATH' ) || exit;

/**
 * Editorial tools in the admin:
 *   - the park list with city, state and acres columns and a state filter (67,000 rows need one);
 *   - Parks › Activity suggestions: approve the backfill's proposals in bulk, all at once or one activity at a time;
 *   - RecPlanet › Data quality: the numbers behind the counter, each linking to the parks to fix, and a rebuild button;
 *   - RecPlanet › Missing links: old addresses that reached the site and found nothing, with a box to send each one somewhere.
 */
class Tools_Admin {

	const BATCH = 300;

	public static function init(): void {
		add_filter( 'manage_' . POST_PARK . '_posts_columns', [ __CLASS__, 'park_columns' ] );
		add_action( 'manage_' . POST_PARK . '_posts_custom_column', [ __CLASS__, 'park_column' ], 10, 2 );
		add_filter( 'manage_edit-' . POST_PARK . '_sortable_columns', fn( $c ) => $c + [ 'rp_acres' => 'rp_acres', 'rp_city' => 'rp_city' ] );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'park_filters' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'park_query' ] );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		add_action( 'admin_post_rp_approve', [ __CLASS__, 'approve' ] );
		add_action( 'admin_post_rp_rebuild', [ __CLASS__, 'rebuild' ] );
		add_action( 'admin_post_rp_link', [ __CLASS__, 'link' ] );
	}

	// ------------------------------------------------------------------ park list

	public static function park_columns( array $c ): array {
		$out = [];
		foreach ( $c as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['rp_city']  = 'City';
				$out['rp_state'] = 'State';
				$out['rp_acres'] = 'Acres';
			}
		}
		unset( $out['taxonomy-rp_place'], $out['taxonomy-rp_facility'] );
		return $out;
	}

	public static function park_column( string $col, int $id ): void {
		if ( 'rp_city' === $col ) {
			echo esc_html( (string) get_post_meta( $id, 'rp_city', true ) );
		} elseif ( 'rp_state' === $col ) {
			$c = strtolower( (string) get_post_meta( $id, 'rp_country', true ) ) ?: 'us';
			echo esc_html( (string) get_post_meta( $id, 'rp_state', true ) . ( 'us' === $c ? '' : ' · ' . strtoupper( $c ) ) );
		} elseif ( 'rp_acres' === $col ) {
			$a = get_post_meta( $id, 'rp_acreage', true );
			echo '' === $a || null === $a ? '<span style="color:#d63638">none</span>' : esc_html( format_acres( (float) $a ) );
		}
	}

	public static function park_filters( string $type ): void {
		if ( POST_PARK !== $type ) {
			return;
		}
		$cur  = sanitize_text_field( wp_unslash( $_GET['rp_state'] ?? '' ) );
		$miss = sanitize_key( $_GET['rp_missing'] ?? '' );
		echo '<select name="rp_state"><option value="">Every state</option>';
		foreach ( us_states() as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $cur, $code, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select> <select name="rp_missing"><option value="">Everything</option>';
		foreach ( [ 'acres' => 'Without acreage', 'coords' => 'Without coordinates', 'city' => 'Without a city', 'activities' => 'Without activities', 'suggestions' => 'With activity suggestions', 'photo' => 'Without a photo' ] as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $miss, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}

	public static function park_query( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || POST_PARK !== $q->get( 'post_type' ) ) {
			return;
		}
		$meta = (array) $q->get( 'meta_query' ) ?: [];
		$st   = sanitize_text_field( wp_unslash( $_GET['rp_state'] ?? '' ) );
		if ( '' !== $st ) {
			$meta[] = [ 'key' => 'rp_state', 'value' => strtoupper( $st ) ];
		}
		switch ( sanitize_key( $_GET['rp_missing'] ?? '' ) ) {
			case 'acres':
				$meta[] = [ 'relation' => 'OR', [ 'key' => 'rp_acreage', 'compare' => 'NOT EXISTS' ], [ 'key' => 'rp_acreage', 'value' => '' ] ];
				break;
			case 'coords':
				$meta[] = [ 'relation' => 'OR', [ 'key' => 'rp_lat', 'compare' => 'NOT EXISTS' ], [ 'key' => 'rp_lat', 'value' => '' ] ];
				break;
			case 'city':
				$meta[] = [ 'relation' => 'OR', [ 'key' => 'rp_city', 'compare' => 'NOT EXISTS' ], [ 'key' => 'rp_city', 'value' => '' ] ];
				break;
			case 'suggestions':
				$meta[] = [ 'key' => 'rp_activity_suggestions', 'compare' => 'EXISTS' ];
				break;
			case 'photo':
				$meta[] = [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ];
				break;
			case 'activities':
				$q->set( 'tax_query', [ [ 'taxonomy' => TAX_ACTIVITY, 'operator' => 'NOT EXISTS' ] ] );
				break;
		}
		if ( $meta ) {
			$q->set( 'meta_query', $meta );
		}
		$orderby = $q->get( 'orderby' );
		if ( 'rp_acres' === $orderby ) {
			$q->set( 'meta_key', 'rp_acreage' );
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( 'rp_city' === $orderby ) {
			$q->set( 'meta_key', 'rp_city' );
			$q->set( 'orderby', 'meta_value' );
		}
	}

	// ------------------------------------------------------------------ menus

	public static function menu(): void {
		$n = self::suggestion_count();
		add_submenu_page( 'edit.php?post_type=' . POST_PARK, 'Activity suggestions', 'Suggestions' . ( $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . $n . '</span></span>' : '' ), 'edit_rp_parks', 'rp-suggestions', [ __CLASS__, 'suggestions' ] );
		add_submenu_page( 'rp-inbox', 'Missing links', 'Missing links', 'edit_rp_parks', 'rp-links', [ __CLASS__, 'links' ] );
		add_submenu_page( 'rp-inbox', 'Data quality', 'Data quality', 'edit_rp_parks', 'rp-quality', [ __CLASS__, 'quality' ] );
	}

	public static function suggestion_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp_activity_suggestions'" );
	}

	// ------------------------------------------------------------------ suggestions

	public static function suggestions(): void {
		global $wpdb;
		$rows  = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'rp_activity_suggestions'" );
		$count = [];
		foreach ( $rows as $json ) {
			foreach ( (array) json_decode( (string) $json, true ) as $a ) {
				$count[ $a ] = ( $count[ $a ] ?? 0 ) + 1;
			}
		}
		arsort( $count );
		$done = (int) ( $_GET['done'] ?? 0 );
		echo '<div class="wrap"><h1>Activity suggestions</h1>';
		if ( $done ) {
			echo '<div class="notice notice-success"><p>Applied to ' . $done . ' parks.</p></div>';
		}
		echo '<p>Parks with no activities on record get suggestions from their name, tags and description (run from the command line with <code>wp recplanet backfill</code>). Approving adds the activity to the park; the park&rsquo;s page, the atlas and the counts update at once. ' . count( $rows ) . ' parks are waiting.</p>';
		if ( ! $rows ) {
			echo '<p>Nothing waiting.</p></div>';
			return;
		}
		$form = fn( string $activity, string $label, string $class = 'button' ) => '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . wp_nonce_field( 'rp_approve', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_approve"><input type="hidden" name="activity" value="' . esc_attr( $activity ) . '"><button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
		echo '<p>' . $form( '*', 'Approve every suggestion (' . count( $rows ) . ' parks)', 'button button-primary' ) . ' &nbsp; <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . POST_PARK . '&rp_missing=suggestions' ) ) . '">Review park by park</a></p>';
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>Activity</th><th>Parks</th><th></th></tr></thead><tbody>';
		foreach ( $count as $a => $n ) {
			echo '<tr><td>' . esc_html( $a ) . '</td><td>' . (int) $n . '</td><td>' . $form( $a, 'Approve ' . $a ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** Applies up to BATCH parks per request and comes back for more, so a big queue never times out. */
	public static function approve(): void {
		check_admin_referer( 'rp_approve' );
		if ( ! current_user_can( 'edit_rp_parks' ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		$only = sanitize_text_field( wp_unslash( $_POST['activity'] ?? '*' ) );
		$done = (int) ( $_POST['done'] ?? 0 );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'rp_activity_suggestions' LIMIT %d", self::BATCH * 2 ) );
		$n    = 0;
		Counter::suspend( true );
		foreach ( $rows as $r ) {
			$s     = json_decode( $r->meta_value, true ) ?: [];
			$apply = '*' === $only ? $s : array_values( array_intersect( $s, [ $only ] ) );
			if ( ! $apply ) {
				continue;
			}
			wp_set_object_terms( (int) $r->post_id, $apply, TAX_ACTIVITY, true );
			$left = array_values( array_diff( $s, $apply ) );
			$left ? update_post_meta( (int) $r->post_id, 'rp_activity_suggestions', wp_json_encode( $left ) ) : delete_post_meta( (int) $r->post_id, 'rp_activity_suggestions' );
			Index::upsert( (int) $r->post_id );
			if ( ++$n >= self::BATCH ) {
				break;
			}
		}
		Counter::suspend( false );
		$done += $n;
		$more = $n >= self::BATCH && ( '*' === $only || (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp_activity_suggestions' AND meta_value LIKE %s", '%' . $wpdb->esc_like( '"' . $only . '"' ) . '%' ) ) );
		if ( $more ) {
			// Hand the browser the next batch through a self-submitting form.
			echo '<!doctype html><title>Approving…</title><p style="font:16px sans-serif;padding:30px">Approved ' . (int) $done . ' so far, continuing…</p><form id="f" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'rp_approve', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_approve"><input type="hidden" name="activity" value="' . esc_attr( $only ) . '"><input type="hidden" name="done" value="' . (int) $done . '"></form><script>document.getElementById("f").submit()</script>';
			exit;
		}
		Counter::rebuild_rollups();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . POST_PARK . '&page=rp-suggestions&done=' . $done ) );
		exit;
	}

	// ------------------------------------------------------------------ data quality

	public static function quality(): void {
		global $wpdb;
		$t = table( 'park_index' );
		$q = fn( $sql ) => (int) $wpdb->get_var( $sql );
		$w = Counter::get( 'world', 'world' );
		$list = fn( string $k ) => admin_url( 'edit.php?post_type=' . POST_PARK . '&rp_missing=' . $k );
		$rows = [
			[ 'Published parks', $q( "SELECT COUNT(*) FROM $t" ), admin_url( 'edit.php?post_type=' . POST_PARK ) ],
			[ 'Without acreage (not counted)', $q( "SELECT COUNT(*) FROM $t WHERE acres IS NULL" ), $list( 'acres' ) ],
			[ 'Without coordinates (not on the map)', $q( "SELECT COUNT(*) FROM $t WHERE lat IS NULL" ), $list( 'coords' ) ],
			[ 'Without a city', $q( "SELECT COUNT(*) FROM $t WHERE city = ''" ), $list( 'city' ) ],
			[ 'Without activities', $q( "SELECT COUNT(*) FROM $t WHERE activity_bits = 0" ), $list( 'activities' ) ],
			[ 'With a photo', $q( "SELECT COUNT(*) FROM $t WHERE has_photo = 1" ), '' ],
			[ 'Activity suggestions waiting', self::suggestion_count(), admin_url( 'edit.php?post_type=' . POST_PARK . '&page=rp-suggestions' ) ],
			[ 'Photos awaiting approval', Photo_Admin::pending(), admin_url( 'edit.php?post_status=pending&post_type=' . POST_PHOTO ) ],
			[ 'Redirects on file', $q( "SELECT COUNT(*) FROM " . table( 'redirects' ) ), admin_url( 'admin.php?page=rp-links' ) ],
		];
		$next = wp_next_scheduled( 'rp_nightly_rebuild' );
		echo '<div class="wrap"><h1>Data quality</h1>';
		if ( ! empty( $_GET['scheduled'] ) ) {
			echo '<div class="notice notice-success"><p>Rebuild scheduled. It runs within a few minutes and takes about five.</p></div>';
		}
		echo '<p>The counter reads <strong>' . esc_html( format_acres( $w['acres'] ) ) . '</strong> acres across <strong>' . esc_html( number_format( $w['count'] ) ) . '</strong> parks. It is re-summed every night' . ( $next ? ', next at ' . esc_html( wp_date( 'j M H:i', $next ) ) : '' ) . '.</p>';
		echo '<table class="widefat striped" style="max-width:640px"><tbody>';
		foreach ( $rows as [ $label, $n, $url ] ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td style="text-align:right"><strong>' . esc_html( number_format( $n ) ) . '</strong></td><td>' . ( $url ? '<a href="' . esc_url( $url ) . '">open</a>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px">' . wp_nonce_field( 'rp_rebuild', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_rebuild"><button class="button">Rebuild the index and counter now</button> <span class="description">Only needed after a bulk change outside the editor.</span></form></div>';
	}

	public static function rebuild(): void {
		check_admin_referer( 'rp_rebuild' );
		if ( ! current_user_can( 'edit_rp_parks' ) ) {
			wp_die( 'Not allowed.' );
		}
		if ( ! wp_next_scheduled( 'rp_nightly_rebuild' ) || wp_next_scheduled( 'rp_nightly_rebuild' ) > time() + 120 ) {
			wp_schedule_single_event( time() + 30, 'rp_nightly_rebuild' );
		}
		spawn_cron();
		wp_safe_redirect( admin_url( 'admin.php?page=rp-quality&scheduled=1' ) );
		exit;
	}

	// ------------------------------------------------------------------ missing links

	public static function links(): void {
		global $wpdb;
		$misses = $wpdb->get_results( "SELECT * FROM " . table( 'misses' ) . " ORDER BY hits DESC, last_seen DESC LIMIT 200" );
		$manual = $wpdb->get_results( "SELECT * FROM " . table( 'redirects' ) . " WHERE kind = 'manual' ORDER BY hits DESC LIMIT 200" );
		echo '<div class="wrap"><h1>Missing links</h1>';
		if ( ! empty( $_GET['linked'] ) ) {
			echo '<div class="notice notice-success"><p>Redirect saved.</p></div>';
		}
		echo '<p>Addresses that reached the site and found nothing, most visited first. Old-site addresses are redirected automatically; anything listed here is a link the site does not know. Give it a destination and it becomes a permanent redirect.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Missing address</th><th>Hits</th><th>Last seen</th><th>Came from</th><th style="width:360px">Send to</th></tr></thead><tbody>';
		foreach ( $misses as $m ) {
			echo '<tr><td><code>/' . esc_html( $m->path ) . '</code></td><td>' . (int) $m->hits . '</td><td>' . esc_html( $m->last_seen ) . '</td><td style="max-width:220px;overflow:hidden;text-overflow:ellipsis">' . esc_html( $m->referrer ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'rp_link', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_link"><input type="hidden" name="old" value="' . esc_attr( $m->path ) . '"><input type="text" name="new" placeholder="/tx/plano/ or a park address" style="width:230px"> <button class="button">Redirect</button> <button class="button-link-delete" name="drop" value="1">Ignore</button></form></td></tr>';
		}
		if ( ! $misses ) {
			echo '<tr><td colspan="5">Nothing missing has been requested yet.</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2 style="margin-top:28px">Redirects added by hand</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">' . wp_nonce_field( 'rp_link', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_link"><input type="text" name="old" placeholder="/old-address" style="width:260px" required> → <input type="text" name="new" placeholder="/new-address/" style="width:260px" required> <button class="button button-primary">Add redirect</button></form>';
		echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>From</th><th>To</th><th>Hits</th><th></th></tr></thead><tbody>';
		foreach ( $manual as $r ) {
			echo '<tr><td><code>/' . esc_html( $r->old_path ) . '</code></td><td><code>' . esc_html( $r->new_path ) . '</code></td><td>' . (int) $r->hits . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'rp_link', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="rp_link"><input type="hidden" name="old" value="' . esc_attr( $r->old_path ) . '"><button class="button-link-delete" name="remove" value="1">Remove</button></form></td></tr>';
		}
		if ( ! $manual ) {
			echo '<tr><td colspan="4">None yet.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function link(): void {
		check_admin_referer( 'rp_link' );
		if ( ! current_user_can( 'edit_rp_parks' ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		$old = trim( sanitize_text_field( wp_unslash( $_POST['old'] ?? '' ) ), '/' );
		$new = trim( sanitize_text_field( wp_unslash( $_POST['new'] ?? '' ) ) );
		if ( '' !== $old ) {
			if ( ! empty( $_POST['remove'] ) ) {
				$wpdb->delete( table( 'redirects' ), [ 'old_path' => $old, 'kind' => 'manual' ] );
			} elseif ( ! empty( $_POST['drop'] ) ) {
				$wpdb->delete( table( 'misses' ), [ 'path' => $old ] );
			} elseif ( '' !== $new ) {
				$path = str_starts_with( $new, 'http' ) ? (string) wp_parse_url( $new, PHP_URL_PATH ) : $new;
				Rewrites::add_redirect( $old, '/' . ltrim( $path, '/' ), 'manual' );
				$wpdb->delete( table( 'misses' ), [ 'path' => $old ] );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rp-links&linked=1' ) );
		exit;
	}

	/** Called from the 404 path when nothing matched. Keeps the table small: real-looking pages only, 5,000 rows at most. */
	public static function log_miss( string $path ): void {
		global $wpdb;
		if ( '' === $path || strlen( $path ) > 180 || preg_match( '#\.(php|xml|txt|json|js|css|map|ico|png|jpe?g|gif|svg|webp|woff2?|ttf|zip|env|sql|bak)$#i', $path ) || preg_match( '#^(wp-|xmlrpc|\.well-known|cgi-bin|vendor|admin|wp/|feed)#i', $path ) ) {
			return;
		}
		$ref = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ), 0, 190 );
		$wpdb->query( $wpdb->prepare( "INSERT INTO " . table( 'misses' ) . " (path, hits, referrer, last_seen) VALUES (%s, 1, %s, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, referrer = IF(%s <> '', %s, referrer), last_seen = %s", $path, $ref, current_time( 'mysql', true ), $ref, $ref, current_time( 'mysql', true ) ) );
		if ( 0 === wp_rand( 0, 200 ) ) {
			$wpdb->query( "DELETE FROM " . table( 'misses' ) . " WHERE last_seen < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)" );
		}
	}
}
