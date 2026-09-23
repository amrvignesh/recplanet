<?php
namespace RP\CLI;

use RP\Counter;
use RP\Index;
use RP\Rewrites;
use RP\Taxonomies;
use WP_CLI;
use function RP\legacy_slug;
use function RP\table;
use function RP\us_states;
use const RP\ACTIVITIES;
use const RP\POST_PARK;
use const RP\POST_PHOTO;
use const RP\POST_CONTEST;
use const RP\TAX_ACTIVITY;
use const RP\TAX_FACILITY;
use const RP\TAX_PLACE;
use const RP\TAX_STEWARD;

defined( 'ABSPATH' ) || exit;

/**
 * Imports the Drupal 6 tables into WordPress. Re-runnable: everything is keyed on the Drupal nid.
 *
 * Load the exported Drupal tables into the WordPress database with a prefix (default d6_), then:
 *
 *   wp recplanet import parks   [--prefix=d6_] [--since=2026-09-01] [--limit=500] [--dry-run] [--files=/path/to/sites/default/files]
 *   wp recplanet import photos  [--prefix=d6_] [--files=...]
 *   wp recplanet import blogs   [--prefix=d6_]
 *   wp recplanet import redirects [--prefix=d6_]
 *   wp recplanet backfill                       propose activities for parks that have none
 *   wp recplanet approve  [--all | --activity=Hunting]
 *   wp recplanet rebuild                        index + roll-ups from scratch
 *   wp recplanet report                         data-quality counts
 */
class Import_Command {

	private string $p = 'd6_';
	private array $stats = [];

	/**
	 * Import one content type from the Drupal tables.
	 *
	 * ## OPTIONS
	 *
	 * <what>
	 * : What to import: parks, photos, blogs or redirects.
	 *
	 * [--prefix=<prefix>]
	 * : Prefix the Drupal tables were loaded under. Default d6_.
	 *
	 * [--since=<date>]
	 * : Only nodes changed on or after this date, for delta runs.
	 *
	 * [--limit=<n>]
	 * : Stop after this many nodes. For trial runs.
	 *
	 * [--dry-run]
	 * : Count what would be created or updated without writing.
	 *
	 * [--files=<path>]
	 * : Absolute path of the copied sites/default/files folder, for images.
	 */
	public function import( array $args, array $assoc ): void {
		$this->p = $assoc['prefix'] ?? 'd6_';
		$this->assert_tables();
		switch ( $args[0] ?? '' ) {
			case 'parks':     $this->parks( $assoc ); break;
			case 'photos':    $this->photos( $assoc ); break;
			case 'blogs':     $this->blogs( $assoc ); break;
			case 'redirects': $this->redirects( $assoc ); break;
			default: WP_CLI::error( 'Say what to import: parks, photos, blogs or redirects.' );
		}
		WP_CLI::success( wp_json_encode( $this->stats ) );
	}

	private function assert_tables(): void {
		global $wpdb;
		foreach ( [ 'node', 'node_revisions', 'content_type_world_parks', 'content_field_pactivities', 'location', 'location_instance', 'url_alias', 'term_data', 'term_node' ] as $t ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->p . $t ) ) ) {
				WP_CLI::error( "Table {$this->p}{$t} is missing. Load the Drupal export with prefix {$this->p} first." );
			}
		}
	}

	private function t( string $name ): string {
		return $this->p . $name;
	}

	/** nid → WP post id map, built once. */
	private function id_map( string $post_type ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value AS nid, pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = 'rp_legacy_nid' AND p.post_type = %s", $post_type ) );
		$map  = [];
		foreach ( $rows as $r ) {
			$map[ (int) $r->nid ] = (int) $r->post_id;
		}
		return $map;
	}

	// ------------------------------------------------------------------ parks

	private function parks( array $a ): void {
		global $wpdb;
		$since = ! empty( $a['since'] ) ? strtotime( $a['since'] ) : 0;
		$limit = (int) ( $a['limit'] ?? 0 );
		$dry   = isset( $a['dry-run'] );
		$map   = $this->id_map( POST_PARK );

		$sql = "SELECT n.nid, n.vid, n.title, n.status, n.created, n.changed, n.uid,
		               r.body, p.field_pacreage_value AS acres, p.field_pownership_value AS owner, p.field_parkweb_value AS web
		        FROM {$this->t('node')} n
		        JOIN {$this->t('node_revisions')} r ON r.vid = n.vid
		        LEFT JOIN {$this->t('content_type_world_parks')} p ON p.vid = n.vid
		        WHERE n.type = 'world_parks'" . ( $since ? $wpdb->prepare( ' AND n.changed >= %d', $since ) : '' ) . ' ORDER BY n.nid' . ( $limit ? " LIMIT $limit" : '' );
		$nodes = $wpdb->get_results( $sql );
		$total = count( $nodes );
		WP_CLI::log( "$total park nodes to process." );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Parks', $total );
		Counter::suspend( true );

		foreach ( $nodes as $n ) {
			$progress->tick();
			$nid  = (int) $n->nid;
			$loc  = $wpdb->get_row( $wpdb->prepare( "SELECT l.* FROM {$this->t('location_instance')} li JOIN {$this->t('location')} l ON l.lid = li.lid WHERE li.vid = %d ORDER BY li.lid ASC LIMIT 1", $n->vid ) );
			$alias = $wpdb->get_var( $wpdb->prepare( "SELECT dst FROM {$this->t('url_alias')} WHERE src = %s ORDER BY pid DESC LIMIT 1", "node/$nid" ) );
			$acts = $wpdb->get_col( $wpdb->prepare( "SELECT field_pactivities_value FROM {$this->t('content_field_pactivities')} WHERE vid = %d AND field_pactivities_value <> '' ORDER BY delta", $n->vid ) );
			$tags = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM {$this->t('term_node')} tn JOIN {$this->t('term_data')} t ON t.tid = tn.tid WHERE tn.vid = %d AND t.vid = 5", $n->vid ) );
			$meta_desc = $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$this->t('nodewords')} WHERE type = 5 AND id = %d AND name = 'description' LIMIT 1", $nid ) );

			$country = strtolower( $loc->country ?? '' ) ?: 'us';
			$state   = strtoupper( $loc->province ?? '' );
			$city    = trim( $loc->city ?? '' );
			[ $county, $facilities ] = $this->parse_tags( $tags, $city, $state );
			[ $level, $owner ] = Taxonomies::classify_ownership( (string) $n->owner );
			$slug = $alias ? basename( $alias ) : legacy_slug( $n->title );

			$post = [
				'post_type'     => POST_PARK,
				'post_title'    => $n->title,
				'post_name'     => $slug,
				'post_content'  => $this->clean_html( (string) $n->body ),
				'post_status'   => $n->status ? 'publish' : 'draft',
				'post_date'     => gmdate( 'Y-m-d H:i:s', (int) $n->created ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', (int) $n->created ),
				'post_modified' => gmdate( 'Y-m-d H:i:s', (int) $n->changed ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', (int) $n->changed ),
				'meta_input'    => [
					'rp_legacy_nid'  => $nid,
					'rp_legacy_path' => (string) $alias,
					'rp_acreage'     => null === $n->acres ? '' : (float) $n->acres,
					'rp_website'     => trim( (string) $n->web ),
					'rp_street'      => trim( $loc->street ?? '' ),
					'rp_postal'      => trim( $loc->postal_code ?? '' ),
					'rp_lat'         => isset( $loc->latitude ) && (float) $loc->latitude !== 0.0 ? (float) $loc->latitude : '',
					'rp_lng'         => isset( $loc->longitude ) && (float) $loc->longitude !== 0.0 ? (float) $loc->longitude : '',
					'rp_country'     => $country,
					'rp_state'       => $state,
					'rp_county'      => $county,
					'rp_city'        => $city,
					'rp_verified_on' => gmdate( 'Y-m-d', (int) $n->changed ),
					'rp_meta_description' => (string) $meta_desc,
				],
			];
			if ( isset( $map[ $nid ] ) ) {
				$post['ID'] = $map[ $nid ];
			}
			if ( $dry ) {
				$this->stats[ isset( $post['ID'] ) ? 'would_update' : 'would_create' ] = ( $this->stats[ isset( $post['ID'] ) ? 'would_update' : 'would_create' ] ?? 0 ) + 1;
				continue;
			}
			$id = isset( $post['ID'] ) ? wp_update_post( $post, true ) : wp_insert_post( $post, true );
			if ( is_wp_error( $id ) ) {
				WP_CLI::warning( "nid $nid: " . $id->get_error_message() );
				continue;
			}
			$map[ $nid ] = $id;
			$this->stats[ isset( $post['ID'] ) ? 'updated' : 'created' ] = ( $this->stats[ isset( $post['ID'] ) ? 'updated' : 'created' ] ?? 0 ) + 1;

			// terms
			wp_set_object_terms( $id, array_values( array_intersect( array_map( 'trim', $acts ), ACTIVITIES ) ), TAX_ACTIVITY );
			wp_set_object_terms( $id, $facilities, TAX_FACILITY );
			wp_set_object_terms( $id, [ Taxonomies::steward_term( $level, $owner ) ], TAX_STEWARD );
			$place = Taxonomies::place_term( $country, $state, $county, $city );
			if ( $place ) {
				wp_set_object_terms( $id, [ $place ], TAX_PLACE );
			}
			if ( ! $acts ) {
				$sugg = $this->suggest_activities( $tags, (string) $n->body );
				if ( $sugg ) {
					update_post_meta( $id, 'rp_activity_suggestions', wp_json_encode( $sugg ) );
					$this->stats['suggested'] = ( $this->stats['suggested'] ?? 0 ) + 1;
				}
			}
			// image
			if ( ! empty( $a['files'] ) && ! has_post_thumbnail( $id ) ) {
				$fp = $wpdb->get_var( $wpdb->prepare( "SELECT f.filepath FROM {$this->t('content_field_parkimage')} pi JOIN {$this->t('files')} f ON f.fid = pi.field_parkimage_fid WHERE pi.vid = %d AND pi.field_parkimage_fid > 0 ORDER BY pi.delta LIMIT 1", $n->vid ) );
				if ( $fp ) {
					$this->attach_file( $id, rtrim( $a['files'], '/' ) . '/' . preg_replace( '#^sites/default/files/#', '', $fp ) );
				}
			}
			// redirect if the old alias differs from the new URL
			if ( $alias ) {
				$new = wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
				if ( trim( $alias, '/' ) !== trim( (string) $new, '/' ) ) {
					Rewrites::add_redirect( $alias, (string) $new, 'park' );
				}
			}
			Index::upsert( $id );
		}
		$progress->finish();
		Counter::suspend( false );
		if ( ! $dry ) {
			Counter::rebuild_rollups();
		}
	}

	/**
	 * "Irving Texas Playgrounds" -> facility "playgrounds"; "Prince George's County Maryland Parks" -> county.
	 * Returns [county, facility slugs].
	 */
	private function parse_tags( array $tags, string $city, string $state ): array {
		$county = '';
		$fac    = [];
		$stname = us_states()[ $state ] ?? '';
		foreach ( $tags as $t ) {
			$t  = trim( $t );
			$tl = strtolower( $t );
			$rest = null;
			if ( $city && str_starts_with( $tl, strtolower( $city ) ) ) {
				$rest = trim( substr( $t, strlen( $city ) ) );
			} elseif ( preg_match( '/^(.+? (County|Parish)) ' . preg_quote( $stname, '/' ) . ' (.+)$/i', $t, $m ) && $stname ) {
				$county = $county ?: $m[1];
				$rest   = $m[3];
			} elseif ( $stname && str_starts_with( $tl, strtolower( $stname ) ) ) {
				$rest = trim( substr( $t, strlen( $stname ) ) );
			}
			if ( null === $rest ) {
				continue;
			}
			if ( $stname && str_starts_with( strtolower( $rest ), strtolower( $stname ) ) ) {
				$rest = trim( substr( $rest, strlen( $stname ) ) );
			}
			$rest = strtolower( trim( $rest ) );
			$rest = str_replace( [ 'disc-golf', 'roller-hockey', 'splash pads' ], [ 'disc golf', 'roller hockey', 'spraygrounds' ], $rest );
			if ( '' !== $rest && strlen( $rest ) < 60 ) {
				$fac[] = $rest;
			}
		}
		return [ $county, array_values( array_unique( $fac ) ) ];
	}

	/** Activities implied by tags or description text, for parks that have none. */
	private function suggest_activities( array $tags, string $body ): array {
		$map = [
			'playground' => 'Playground', 'basketball' => 'Basketball', 'picnic' => 'Picnicking', 'baseball' => 'Baseball',
			'softball' => 'Softball', 'soccer' => 'Soccer', 'tennis' => 'Tennis', 'trail' => 'Hiking', 'hike' => 'Hiking',
			'volleyball' => 'Volleyball', 'dog park' => 'Dog-Park', 'skate' => 'Skate Park', 'sprayground' => 'Sprayground',
			'splash' => 'Sprayground', 'swimming' => 'Swimming', 'pool' => 'Swimming', 'campground' => 'Camping',
			'boat ramp' => 'Boating', 'horseshoe' => 'Horseshoes', 'handball' => 'Handball', 'golf course' => 'Golf',
			'disc golf' => 'Disc-Golf', 'disc-golf' => 'Disc-Golf', 'beach' => 'Swimming', 'fishing' => 'Fishing',
			'archery' => 'Archery Range', 'bocce' => 'Bocce', 'equestrian' => 'Horse Back Riding', 'bike' => 'Biking',
			'roller' => 'Roller-hockey', 'hunting' => 'Hunting', 'wildlife management' => 'Hunting', 'waterfowl' => 'Hunting',
		];
		$hay = strtolower( implode( ' | ', $tags ) . ' | ' . wp_strip_all_tags( $body ) );
		$out = [];
		foreach ( $map as $needle => $act ) {
			if ( str_contains( $hay, $needle ) ) {
				$out[ $act ] = true;
			}
		}
		return array_keys( $out );
	}

	/** Strip the pasted inline styles and Word leftovers; keep paragraphs, links, lists, emphasis. */
	private function clean_html( string $html ): string {
		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		$html = preg_replace( '/<(span|font|div)[^>]*>/i', '', $html );
		$html = preg_replace( '#</(span|font|div)>#i', '', $html );
		$html = wp_kses( $html, [
			'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'ul' => [], 'ol' => [], 'li' => [],
			'a' => [ 'href' => true, 'title' => true, 'rel' => true ], 'h2' => [], 'h3' => [], 'blockquote' => [],
		] );
		$html = preg_replace( '/&nbsp;/', ' ', $html );
		$html = preg_replace( '/<p>\s*<\/p>/', '', $html );
		return trim( preg_replace( '/\s+/', ' ', $html ) );
	}

	private function attach_file( int $post_id, string $path ): void {
		if ( ! file_exists( $path ) ) {
			$this->stats['missing_files'] = ( $this->stats['missing_files'] ?? 0 ) + 1;
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$tmp = wp_tempnam( basename( $path ) );
		copy( $path, $tmp );
		$att = media_handle_sideload( [ 'name' => basename( $path ), 'tmp_name' => $tmp ], $post_id );
		if ( ! is_wp_error( $att ) ) {
			set_post_thumbnail( $post_id, $att );
			$this->stats['images'] = ( $this->stats['images'] ?? 0 ) + 1;
		}
	}

	// ------------------------------------------------------------------ photos

	private function photos( array $a ): void {
		global $wpdb;
		$map   = $this->id_map( POST_PHOTO );
		$parks = $this->id_map( POST_PARK );
		$contest = $this->legacy_contest();
		$rows  = $wpdb->get_results( "SELECT n.nid, n.vid, n.title, n.status, n.created, n.uid, u.name AS author, r.body, f.filepath,
		                                     c.field_contest_star_rating_value AS stars, c.field_contest_comment_value AS comment
		                              FROM {$this->t('node')} n
		                              JOIN {$this->t('node_revisions')} r ON r.vid = n.vid
		                              JOIN {$this->t('content_type_photo_contest')} c ON c.vid = n.vid
		                              LEFT JOIN {$this->t('files')} f ON f.fid = c.field_photo_contest_fid
		                              LEFT JOIN {$this->t('users')} u ON u.uid = n.uid
		                              WHERE n.type = 'photo_contest' ORDER BY n.nid" );
		foreach ( $rows as $r ) {
			$v     = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(AVG(value),0) AS pct FROM {$this->t('votingapi_vote')} WHERE content_type = 'node' AND content_id = %d", $r->nid ) );
			$votes = (int) $v->n;
			$score = round( (float) $v->pct / 20, 1 );   // Fivestar stored 20..100 percent; the old site showed SUM/20
			$post  = [
				'post_type' => POST_PHOTO, 'post_title' => $r->title, 'post_content' => $this->clean_html( (string) $r->body ),
				'post_status' => $r->status ? 'publish' : 'draft', 'post_date' => gmdate( 'Y-m-d H:i:s', (int) $r->created ),
				'meta_input' => [ 'rp_legacy_nid' => (int) $r->nid, 'rp_contributor' => (string) $r->author, 'rp_contest_id' => $contest, 'rp_legacy_votes' => $votes, 'rp_legacy_score' => $score, 'rp_votes' => $votes, 'rp_score' => $score ],
			];
			if ( isset( $map[ (int) $r->nid ] ) ) {
				$post['ID'] = $map[ (int) $r->nid ];
			}
			$id = isset( $post['ID'] ) ? wp_update_post( $post ) : wp_insert_post( $post );
			if ( ! $id ) {
				continue;
			}
			$this->stats['photos'] = ( $this->stats['photos'] ?? 0 ) + 1;
			$tags = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM {$this->t('term_node')} tn JOIN {$this->t('term_data')} t ON t.tid = tn.tid WHERE tn.vid = %d AND t.vid = 6", $r->vid ) );
			if ( $tags ) {
				wp_set_object_terms( $id, $tags, 'post_tag' );
			}
			if ( ! empty( $a['files'] ) && $r->filepath && ! has_post_thumbnail( $id ) ) {
				$this->attach_file( $id, rtrim( $a['files'], '/' ) . '/' . preg_replace( '#^sites/default/files/#', '', $r->filepath ) );
			}
		}
		// The dormant "upload a park picture" type: a photo pinned to a park.
		$rows = $wpdb->get_results( "SELECT n.nid, n.title, n.created, u.name AS author, c.field_park_pic_node_nid AS park_nid, f.filepath
		                              FROM {$this->t('node')} n JOIN {$this->t('content_type_upload_park_picture')} c ON c.vid = n.vid
		                              LEFT JOIN {$this->t('content_field_park_picture')} pp ON pp.vid = n.vid AND pp.delta = 0
		                              LEFT JOIN {$this->t('files')} f ON f.fid = pp.field_park_picture_fid
		                              LEFT JOIN {$this->t('users')} u ON u.uid = n.uid WHERE n.type = 'upload_park_picture'" );
		foreach ( $rows as $r ) {
			if ( isset( $map[ (int) $r->nid ] ) ) {
				continue;
			}
			$park = $parks[ (int) $r->park_nid ] ?? 0;
			$id   = wp_insert_post( [ 'post_type' => POST_PHOTO, 'post_title' => $park ? get_the_title( $park ) : $r->title, 'post_status' => 'publish',
				'post_date' => gmdate( 'Y-m-d H:i:s', (int) $r->created ), 'meta_input' => [ 'rp_legacy_nid' => (int) $r->nid, 'rp_contributor' => (string) $r->author, 'rp_park_id' => $park ] ] );
			if ( $id && ! empty( $a['files'] ) && $r->filepath ) {
				$this->attach_file( $id, rtrim( $a['files'], '/' ) . '/' . preg_replace( '#^sites/default/files/#', '', $r->filepath ) );
			}
			$this->stats['park_pictures'] = ( $this->stats['park_pictures'] ?? 0 ) + 1;
		}
	}

	/** The archived contest that holds the 2012 to 2024 entries. */
	private function legacy_contest(): int {
		$found = get_posts( [ 'post_type' => POST_CONTEST, 'name' => 'photo-contest-2012-2024', 'posts_per_page' => 1, 'post_status' => 'any' ] );
		if ( $found ) {
			return $found[0]->ID;
		}
		return (int) wp_insert_post( [ 'post_type' => POST_CONTEST, 'post_title' => 'Photo contest, 2012 to 2024', 'post_name' => 'photo-contest-2012-2024', 'post_status' => 'publish',
			'post_content' => '<p>Every entry and every vote from the original RecPlanet photo contest, kept as they were.</p>',
			'meta_input' => [ 'rp_status' => 'closed', 'rp_opens_at' => '2012-01-13', 'rp_closes_at' => '2024-11-26', 'rp_voting_closes_at' => '2024-11-26' ] ] );
	}

	// ------------------------------------------------------------------ blogs

	private function blogs( array $a ): void {
		global $wpdb;
		$map  = $this->id_map( 'post' );
		$rows = $wpdb->get_results( "SELECT n.nid, n.vid, n.title, n.status, n.created, n.changed, u.name AS author, r.body, a.dst
		                              FROM {$this->t('node')} n JOIN {$this->t('node_revisions')} r ON r.vid = n.vid
		                              LEFT JOIN {$this->t('users')} u ON u.uid = n.uid
		                              LEFT JOIN {$this->t('url_alias')} a ON a.src = CONCAT('node/', n.nid)
		                              WHERE n.type IN ('blogs','blog') ORDER BY n.nid" );
		foreach ( $rows as $r ) {
			$tags = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM {$this->t('term_node')} tn JOIN {$this->t('term_data')} t ON t.tid = tn.tid WHERE tn.vid = %d AND t.vid = 9", $r->vid ) );
			$post = [ 'post_type' => 'post', 'post_title' => $r->title, 'post_name' => $r->dst ? basename( $r->dst ) : legacy_slug( $r->title ),
				'post_content' => $this->clean_html( (string) $r->body ), 'post_status' => $r->status ? 'publish' : 'draft',
				'post_date' => gmdate( 'Y-m-d H:i:s', (int) $r->created ), 'tags_input' => $tags,
				'meta_input' => [ 'rp_legacy_nid' => (int) $r->nid, 'rp_contributor' => (string) $r->author ] ];
			if ( isset( $map[ (int) $r->nid ] ) ) {
				$post['ID'] = $map[ (int) $r->nid ];
			}
			$id = isset( $post['ID'] ) ? wp_update_post( $post ) : wp_insert_post( $post );
			if ( $id && $r->dst ) {
				Rewrites::add_redirect( $r->dst, (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ), 'blog' );
			}
			$this->stats['blogs'] = ( $this->stats['blogs'] ?? 0 ) + 1;
		}
	}

	// ------------------------------------------------------------------ redirects for the 50,309 term aliases

	private function redirects( array $a ): void {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT a.dst, t.name FROM {$this->t('url_alias')} a JOIN {$this->t('term_data')} t ON CONCAT('taxonomy/term/', t.tid) = a.src WHERE a.dst LIKE 'category/%' AND t.vid = 5" );
		$states = array_flip( array_map( 'strtolower', us_states() ) );   // name -> code
		foreach ( $rows as $r ) {
			$target = $this->target_for_tag( $r->name, $states );
			if ( $target ) {
				Rewrites::add_redirect( $r->dst, $target, 'tag' );
				$this->stats['tag_redirects'] = ( $this->stats['tag_redirects'] ?? 0 ) + 1;
			} else {
				$this->stats['tag_unparsed'] = ( $this->stats['tag_unparsed'] ?? 0 ) + 1;
			}
		}
	}

	/** "Irving Texas Playgrounds" -> /tx/irving/playgrounds ; "Texas State Parks" -> /tx/state-parks ; else state page. */
	private function target_for_tag( string $tag, array $states ): ?string {
		$tl = strtolower( trim( $tag ) );
		foreach ( $states as $name => $code ) {
			$pos = strpos( $tl, ' ' . $name . ' ' );
			if ( false !== $pos ) {
				$city = trim( substr( $tag, 0, $pos ) );
				$fac  = trim( substr( $tl, $pos + strlen( $name ) + 2 ) );
				$fac  = str_replace( [ 'disc-golf', 'roller-hockey' ], [ 'disc golf', 'roller hockey' ], $fac );
				return '/' . strtolower( $code ) . '/' . legacy_slug( $city ) . '/' . legacy_slug( $fac );
			}
			if ( str_starts_with( $tl, $name . ' ' ) ) {
				return '/' . strtolower( $code ) . '/' . legacy_slug( substr( $tl, strlen( $name ) + 1 ) );
			}
		}
		return null;
	}

	// ------------------------------------------------------------------ backfill

	/**
	 * Propose activities for parks that have none, from tags and text. Approve with `wp recplanet approve`.
	 */
	public function backfill( array $args, array $assoc ): void {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish'
			AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = p.ID AND tt.taxonomy = %s)", POST_PARK, TAX_ACTIVITY ) );
		$n = 0;
		foreach ( $ids as $id ) {
			$tags = wp_get_object_terms( (int) $id, TAX_FACILITY, [ 'fields' => 'names' ] );
			$s    = $this->suggest_activities( is_array( $tags ) ? $tags : [], get_post_field( 'post_content', (int) $id ) );
			if ( $s ) {
				update_post_meta( (int) $id, 'rp_activity_suggestions', wp_json_encode( $s ) );
				$n++;
			}
		}
		WP_CLI::success( count( $ids ) . " parks have no activities; suggestions written for $n. Review with: wp recplanet approve --all, or per activity: --activity=Hunting" );
	}

	/**
	 * Apply activity suggestions in bulk.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Apply every pending suggestion.
	 *
	 * [--activity=<name>]
	 * : Apply only suggestions for this activity, e.g. Hunting.
	 */
	public function approve( array $args, array $assoc ): void {
		global $wpdb;
		$only = $assoc['activity'] ?? '';
		if ( ! $only && ! isset( $assoc['all'] ) ) {
			WP_CLI::error( 'Say --all or --activity=<name>.' );
		}
		$rows = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'rp_activity_suggestions'" );
		$n = 0;
		Counter::suspend( true );
		foreach ( $rows as $r ) {
			$s = json_decode( $r->meta_value, true ) ?: [];
			$apply = $only ? array_values( array_intersect( $s, [ $only ] ) ) : $s;
			if ( ! $apply ) {
				continue;
			}
			wp_set_object_terms( (int) $r->post_id, $apply, TAX_ACTIVITY, true );
			$left = array_values( array_diff( $s, $apply ) );
			$left ? update_post_meta( (int) $r->post_id, 'rp_activity_suggestions', wp_json_encode( $left ) ) : delete_post_meta( (int) $r->post_id, 'rp_activity_suggestions' );
			Index::upsert( (int) $r->post_id );
			$n++;
		}
		Counter::suspend( false );
		WP_CLI::success( "Applied suggestions on $n parks." );
	}

	/** Rebuild the index and every roll-up from scratch. */
	public function rebuild( array $args, array $assoc ): void {
		$n = Index::rebuild_all( fn( $done, $total ) => WP_CLI::log( "$done / $total" ) );
		$w = Counter::get( 'world', 'world' );
		WP_CLI::success( "$n parks indexed. Counter: " . \RP\format_acres( $w['acres'] ) . ' acres.' );
	}

	/** Data-quality counts for the editor. */
	public function report( array $args, array $assoc ): void {
		global $wpdb;
		$t = table( 'park_index' );
		$q = fn( $sql ) => (int) $wpdb->get_var( $sql );
		WP_CLI::log( 'Published parks:        ' . $q( "SELECT COUNT(*) FROM $t" ) );
		WP_CLI::log( 'Without acreage:        ' . $q( "SELECT COUNT(*) FROM $t WHERE acres IS NULL" ) );
		WP_CLI::log( 'Without coordinates:    ' . $q( "SELECT COUNT(*) FROM $t WHERE lat IS NULL" ) );
		WP_CLI::log( 'Without a city:         ' . $q( "SELECT COUNT(*) FROM $t WHERE city = ''" ) );
		WP_CLI::log( 'US without a state:     ' . $q( "SELECT COUNT(*) FROM $t WHERE country = 'us' AND state = ''" ) );
		WP_CLI::log( 'Without activities:     ' . $q( "SELECT COUNT(*) FROM $t WHERE activity_bits = 0" ) );
		WP_CLI::log( 'With a photo:           ' . $q( "SELECT COUNT(*) FROM $t WHERE has_photo = 1" ) );
		WP_CLI::log( 'Pending suggestions:    ' . $q( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rp_activity_suggestions'" ) );
		WP_CLI::log( 'Redirects on file:      ' . $q( "SELECT COUNT(*) FROM " . table( 'redirects' ) ) );
		WP_CLI::log( 'Duplicate title+city:   ' . $q( "SELECT COUNT(*) FROM (SELECT title, city, state FROM $t GROUP BY title, city, state HAVING COUNT(*) > 1) d" ) );
	}
}

WP_CLI::add_command( 'recplanet', Import_Command::class );
