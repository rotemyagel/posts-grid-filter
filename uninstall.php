<?php
/**
 * Uninstall routine (runs when the plugin is deleted, not on deactivate).
 *
 * Deletes only what the seeder recorded as created by this plugin, never
 * anything that merely shares its post type, taxonomy, or slug. Demo
 * posts go first; then a seeded image or term is deleted only if nothing
 * left on the site still uses it. When that can't be checked, it is kept.
 *
 * @package WMPGF
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

require_once __DIR__ . '/includes/class-wmpgf-post-type.php';

// The plugin is inactive here, and get_terms()/wp_delete_term() fail on
// unregistered taxonomies, so register them for this request.
$wmpgf_post_type = new WMPGF_Post_Type();
$wmpgf_post_type->register_post_type();
$wmpgf_post_type->register_taxonomies();

$owned_ids = static function ( $option ) {
	$ids = get_option( $option );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
};

// 1. The demo page, only if the seeder created it.
$demo_page_id = (int) get_option( 'wmpgf_demo_page_id' );
if ( $demo_page_id && get_option( 'wmpgf_demo_page_owned' ) && 'page' === get_post_type( $demo_page_id ) ) {
	wp_delete_post( $demo_page_id, true );
}

// 2. The demo posts. Hand-made wmpgf_posts are not on the list and stay.
foreach ( $owned_ids( 'wmpgf_seeded_post_ids' ) as $seeded_post_id ) {
	if ( WMPGF_Post_Type::POST_TYPE === get_post_type( $seeded_post_id ) ) {
		wp_delete_post( $seeded_post_id, true );
	}
}

/*
 * The checks below query the tables directly: no WordPress API answers
 * "does anything, in any status, still use this image or term", and a
 * one-off uninstall check must not be served from a cache.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// 3. Seeded images, unless something that survived still uses one: as a
// featured image, in content, in any other post meta (page builders keep
// image URLs there), as the site icon or logo, or by file name in options
// (widgets, theme mods such as a header image), term meta or user meta.
// Transients are caches, not references, so they don't count.
$attachment_in_use = static function ( $attachment_id ) use ( $wpdb ) {
	if ( (int) get_option( 'site_icon' ) === $attachment_id || (int) get_theme_mod( 'custom_logo' ) === $attachment_id ) {
		return true;
	}

	$file   = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	$counts = array(
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
				(string) $attachment_id
			)
		),
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID <> %d AND post_content REGEXP %s",
				$attachment_id,
				'wp-image-' . $attachment_id . '([^0-9]|$)'
			)
		),
	);
	if ( '' !== $file ) {
		$name     = '%' . $wpdb->esc_like( wp_basename( $file ) ) . '%';
		$counts[] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID <> %d AND post_content LIKE %s", $attachment_id, $name ) );
		$counts[] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_value LIKE %s", $attachment_id, $name ) );
		$counts[] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s",
				$name,
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_' ) . '%'
			)
		);
		$counts[] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value LIKE %s", $name ) );
		$counts[] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_value LIKE %s", $name ) );
	}

	foreach ( $counts as $count ) {
		// null means the lookup failed: keep the image.
		if ( null === $count || (int) $count > 0 ) {
			return true;
		}
	}
	return false;
};

foreach ( $owned_ids( 'wmpgf_seeded_attachment_ids' ) as $attachment_id ) {
	if ( 'attachment' === get_post_type( $attachment_id ) && ! $attachment_in_use( $attachment_id ) ) {
		wp_delete_attachment( $attachment_id, true );
	}
}

// 4. Seeded terms, unless a surviving post of any status still has one.
// Counted from the relationships table, because a term's count field
// only covers published posts. Terms the seeder reused were never on the
// list, so they are untouched.
foreach ( $owned_ids( 'wmpgf_seeded_term_ids' ) as $term_id ) {
	foreach ( array( WMPGF_Post_Type::TAX_CATEGORY, WMPGF_Post_Type::TAX_TAG ) as $taxonomy_name ) {
		$found_term = get_term( $term_id, $taxonomy_name );
		if ( ! $found_term instanceof WP_Term ) {
			continue;
		}

		$relationships = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
				$found_term->term_taxonomy_id
			)
		);
		// null means the lookup failed: keep the term.
		if ( null !== $relationships && 0 === (int) $relationships ) {
			wp_delete_term( $term_id, $taxonomy_name );
		}
		break;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

delete_option( 'wmpgf_seeded' );
delete_option( 'wmpgf_demo_page_id' );
delete_option( 'wmpgf_demo_page_owned' );
delete_option( 'wmpgf_seeded_post_ids' );
delete_option( 'wmpgf_seeded_attachment_ids' );
delete_option( 'wmpgf_seeded_term_ids' );
delete_option( 'wmpgf_rewrite_version' );
delete_option( 'wmpgf_category_children' ); // Created by core for hierarchical taxonomies.
