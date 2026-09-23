<?php
/**
 * Titles, descriptions, canonicals, pagination links, structured data, social images, thin-page noindex.
 */

defined( 'ABSPATH' ) || exit;

/** What this page is about, in one line: used by the title, description and social tags. */
function rp_seo_context(): array {
	$c = [ 'title' => '', 'desc' => '', 'canonical' => '', 'image' => RP_THEME_URI . '/assets/img/og-default.png', 'noindex' => false ];
	if ( is_front_page() ) {
		$w = RP\Counter::get( 'world', 'world' );
		$c['title']     = 'RecPlanet: ' . number_format( $w['count'] ) . ' parks and public lands, mapped by hand';
		$c['desc']      = 'Find parks, trails, playgrounds and public land near you. ' . number_format( $w['count'] ) . ' places across ' . RP\format_acres( $w['acres'], 0 ) . ' acres, every one checked by a person, with what you can do there.';
		$c['canonical'] = home_url( '/' );
	} elseif ( is_singular( 'rp_park' ) ) {
		$id    = get_the_ID();
		$acres = get_post_meta( $id, 'rp_acreage', true );
		$place = rp_park_place_line( $id );
		$acts  = wp_get_object_terms( $id, 'rp_activity', [ 'fields' => 'names' ] );
		$c['title']     = get_the_title( $id ) . ( $place ? ', ' . $place : '' ) . ( '' !== $acres ? ': ' . RP\format_acres( (float) $acres, 0 ) . ' acres' : '' );
		$c['desc']      = get_post_meta( $id, 'rp_meta_description', true ) ?: ( rp_park_lead( $id, 160 ) . ( $acts ? ' ' . implode( ', ', array_slice( $acts, 0, 5 ) ) . '.' : '' ) );
		$c['canonical'] = get_permalink( $id );
		if ( has_post_thumbnail( $id ) ) {
			$c['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
	} elseif ( is_tax( 'rp_place' ) ) {
		$t = get_queried_object();
		$s = rp_place_stats( $t );
		$chain = rp_place_chain( $t );
		$level = get_term_meta( $t->term_id, 'rp_level', true );
		$where = 'city' === $level || 'county' === $level ? $t->name . ', ' . ( $chain['terms']['state']->name ?? '' ) : $t->name;
		$c['title']     = $where . ': ' . number_format( $s['count'] ) . ' parks and public lands';
		$c['desc']      = number_format( $s['count'] ) . ' parks, trails and public places in ' . $where . ' across ' . RP\format_acres( $s['acres'], 0 ) . ' acres, with what you can do at each one. Checked by a person, not scraped.';
		$c['canonical'] = get_term_link( $t );
		$c['noindex']   = $s['count'] < 3;
	} elseif ( is_tax( [ 'rp_activity', 'rp_facility', 'rp_steward' ] ) ) {
		$t = get_queried_object();
		$n = number_format( $t->count );
		if ( 'rp_activity' === $t->taxonomy ) {
			$c['title'] = 'Where to ' . rp_activity_verb( $t->name ) . ': ' . $n . ' places';
			$c['desc']  = $n . ' parks and public lands where you can ' . rp_activity_verb( $t->name ) . ', by state and city, each one checked by a person.';
		} elseif ( 'rp_facility' === $t->taxonomy ) {
			$c['title'] = ucfirst( $t->name ) . ': ' . $n . ' places';
			$c['desc']  = $n . ' places with ' . $t->name . ', by state and city, each one checked by a person.';
		} else {
			$c['title'] = 'Managed by ' . $t->name . ': ' . $n . ' places';
			$c['desc']  = $n . ' parks and public lands managed by ' . $t->name . ', with acreage and activities for each.';
		}
		$c['canonical'] = get_term_link( $t );
		$c['noindex']   = $t->count < 3;
	} elseif ( is_post_type_archive( 'rp_contest' ) ) {
		$c['title']     = 'Photo contest: rate the parks, win prizes';
		$c['desc']      = 'Members photograph parks and pin the photos to the place. Anyone can rate them, one to five stars, and prizes are drawn among voters as well as photographers.';
		$c['canonical'] = get_post_type_archive_link( 'rp_contest' );
	} elseif ( is_singular( 'rp_photo' ) ) {
		$id   = get_the_ID();
		$park = (int) get_post_meta( $id, 'rp_park_id', true );
		$c['title']     = get_the_title( $id ) . ( $park ? ' at ' . get_the_title( $park ) : '' ) . ': photo';
		$c['desc']      = 'A photo by ' . ( get_post_meta( $id, 'rp_contributor', true ) ?: get_the_author_meta( 'display_name', get_post_field( 'post_author', $id ) ) ) . ( $park ? ' taken at ' . get_the_title( $park ) : '' ) . '. Rate it from one to five stars.';
		$c['canonical'] = get_permalink( $id );
		if ( has_post_thumbnail( $id ) ) {
			$c['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
	} elseif ( is_page() ) {
		$c['canonical'] = get_permalink();
		$map = [
			'atlas'      => [ 'The atlas: every park and public land on one map', 'Zoom, filter by activity, find what is within a fifteen minute walk. Every dot is a place checked by a person.' ],
			'world'      => [ 'World Parks: every state and country on file', 'Parks and public lands across the United States, state by state, and the world beyond, with counts and acres for each.' ],
			'states'     => [ 'Every US state: parks and public lands by city', 'Pick a state, then a city. Counts and acres for each, every record checked in person.' ],
			'activities' => [ 'Activities: what do you want to do?', 'Every activity on file, from playgrounds to hang-gliding, with the number of places for each.' ],
			'about-us'   => [ 'About RecPlanet', 'Founded by Taylor Marshall in 2008. A hand-made database of parks and public lands, never scraped.' ],
			'rules'      => [ 'Photo contest rules', 'How to enter, what counts, and how prizes are drawn.' ],
		];
		$slug = get_post_field( 'post_name' );
		if ( isset( $map[ $slug ] ) ) {
			[ $c['title'], $c['desc'] ] = $map[ $slug ];
		} else {
			$c['desc'] = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content' ) ), 26, '.' );
		}
	} elseif ( is_singular( 'post' ) ) {
		$c['canonical'] = get_permalink();
		$c['desc']      = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content' ) ), 26, '.' );
		if ( has_post_thumbnail() ) {
			$c['image'] = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		}
	} elseif ( is_home() ) {
		$c['title']     = 'Blog: from the trail notebook';
		$c['desc']      = 'Trips, gear and notes from parks and public lands, by RecPlanet members.';
		$c['canonical'] = get_post_type_archive_link( 'post' ) ?: home_url( '/blog/' );
	} elseif ( is_search() ) {
		$c['noindex'] = true;
	}
	if ( $c['canonical'] ) {
		$c['canonical'] = user_trailingslashit( $c['canonical'] );
		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$c['canonical'] = trailingslashit( $c['canonical'] ) . 'page/' . $paged . '/';
		}
	}
	return $c;
}

add_filter( 'document_title_parts', function ( array $parts ) {
	$c = rp_seo_context();
	if ( $c['title'] ) {
		$parts['title'] = $c['title'];
		unset( $parts['tagline'] );
	}
	return $parts;
}, 20 );
add_filter( 'document_title_separator', fn() => '–' );

add_action( 'wp_head', function () {
	$c = rp_seo_context();
	if ( $c['desc'] ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $c['desc'] ) ) . '">' . "\n";
	}
	if ( $c['canonical'] && ! is_singular() ) {   // WordPress prints its own canonical on singular pages
		echo '<link rel="canonical" href="' . esc_url( $c['canonical'] ) . '">' . "\n";
	}
	if ( $c['noindex'] ) {
		echo '<meta name="robots" content="noindex,follow">' . "\n";
	}
	// Pagination hints for list pages.
	if ( is_tax() || is_post_type_archive() || is_home() ) {
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		$base  = preg_replace( '#page/\d+/$#', '', $c['canonical'] );
		global $wp_query;
		$max = (int) $wp_query->max_num_pages;
		if ( is_tax( 'rp_place' ) ) {
			$max = (int) ceil( rp_place_stats( get_queried_object() )['count'] / 50 );
		}
		if ( $paged > 1 ) {
			echo '<link rel="prev" href="' . esc_url( $paged > 2 ? add_query_arg( 'paged', $paged - 1, $base ) : $base ) . '">' . "\n";
		}
		if ( $paged < $max ) {
			echo '<link rel="next" href="' . esc_url( add_query_arg( 'paged', $paged + 1, $base ) ) . '">' . "\n";
		}
	}
	echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 4 );

/** Jetpack writes the Open Graph tags on WordPress.com; give it our title, description and image. */
add_filter( 'jetpack_open_graph_tags', function ( array $tags ) {
	$c = rp_seo_context();
	if ( $c['title'] ) {
		$tags['og:title'] = $c['title'];
	}
	if ( $c['desc'] ) {
		$tags['og:description'] = wp_strip_all_tags( $c['desc'] );
	}
	$tags['og:image'] = $c['image'];
	$tags['og:image:width']  = 1200;
	$tags['og:image:height'] = 630;
	$tags['twitter:card']    = 'summary_large_image';
	if ( $c['canonical'] ) {
		$tags['og:url'] = $c['canonical'];
	}
	return $tags;
} );
add_filter( 'jetpack_open_graph_image_default', fn() => RP_THEME_URI . '/assets/img/og-default.png' );

/** Jetpack's own meta description would duplicate ours. */
add_filter( 'jetpack_seo_meta_tags_enabled', '__return_false' );
add_filter( 'jetpack_enable_open_graph', '__return_true' );

/** Structured data beyond the park: breadcrumbs everywhere, lists on list pages, the site on the home page. */
add_action( 'wp_head', function () {
	$out = [];
	if ( is_front_page() ) {
		$out[] = [ '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'RecPlanet', 'url' => home_url( '/' ),
			'potentialAction' => [ '@type' => 'SearchAction', 'target' => [ '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}' ) ], 'query-input' => 'required name=search_term_string' ] ];
		$out[] = [ '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'RecPlanet', 'url' => home_url( '/' ), 'logo' => RP_THEME_URI . '/assets/img/logo.png', 'founder' => [ '@type' => 'Person', 'name' => 'Taylor Marshall' ], 'foundingDate' => '2008' ];
	}
	$crumbs = [];
	if ( is_singular( 'rp_park' ) ) {
		$place  = rp_park_place_term( get_the_ID() );
		$crumbs = $place ? rp_place_crumbs( $place ) : [ [ 'World', home_url( '/' ) ] ];
		$crumbs[] = [ get_the_title(), get_permalink() ];
	} elseif ( is_tax( 'rp_place' ) ) {
		$crumbs = rp_place_crumbs( get_queried_object() );
	} elseif ( is_tax() ) {
		$t = get_queried_object();
		$crumbs = [ [ 'World', home_url( '/' ) ], [ ucfirst( str_replace( 'rp_', '', $t->taxonomy ) ), home_url( '/activities/' ) ], [ $t->name, get_term_link( $t ) ] ];
	}
	if ( $crumbs ) {
		$items = [];
		foreach ( $crumbs as $i => [ $name, $url ] ) {
			$items[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url ];
		}
		$out[] = [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
	}
	if ( is_tax() ) {
		$t = get_queried_object();
		if ( 'rp_place' === $t->taxonomy ) {
			[ $w, $a ] = rp_place_where( $t );
			$rows = rp_index_rows( $w, $a, 'acres DESC', 50 );
		} elseif ( 'rp_activity' === $t->taxonomy ) {
			$i    = array_search( $t->name, RP\ACTIVITIES, true );
			$rows = rp_index_rows( '(activity_bits & %d) <> 0', [ 1 << max( 0, (int) $i ) ], 'acres DESC', 50 );
		} else {
			$rows = [];
		}
		if ( $rows ) {
			$items = [];
			foreach ( $rows as $i => $r ) {
				$items[] = [ '@type' => 'ListItem', 'position' => $i + 1, 'name' => $r->title, 'url' => get_permalink( (int) $r->post_id ) ];
			}
			$out[] = [ '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $t->name, 'numberOfItems' => count( $items ), 'itemListElement' => $items ];
		}
	}
	foreach ( $out as $ld ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}, 7 );
