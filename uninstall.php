<?php
/**
 * Uninstall routine (runs when the plugin is deleted, not on deactivate).
 *
 * Deletes only what the seeder recorded as created by this plugin, never
 * anything that merely shares its post type, taxonomy, or slug.
 *
 * @package WMPGF
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wmpgf-post-type.php';

// The plugin is inactive here, and get_terms()/wp_delete_term() fail on
// unregistered taxonomies, so register them for this request.
$wmpgf_post_type = new WMPGF_Post_Type();
$wmpgf_post_type->register_post_type();
$wmpgf_post_type->register_taxonomies();

// An adopted, pre-existing page at the demo slug is not ours to delete.
$demo_page_id = (int) get_option( 'wmpgf_demo_page_id' );
if ( $demo_page_id && get_option( 'wmpgf_demo_page_owned' ) ) {
	wp_delete_post( $demo_page_id, true );
}

// Only the recorded IDs: hand-made wmpgf_posts and their images are kept.
$seeded_post_ids       = get_option( 'wmpgf_seeded_post_ids' );
$seeded_attachment_ids = get_option( 'wmpgf_seeded_attachment_ids' );

if ( is_array( $seeded_post_ids ) ) {
	foreach ( $seeded_post_ids as $seeded_post_id ) {
		if ( WMPGF_Post_Type::POST_TYPE === get_post_type( $seeded_post_id ) ) {
			wp_delete_post( (int) $seeded_post_id, true );
		}
	}
}

if ( is_array( $seeded_attachment_ids ) ) {
	foreach ( $seeded_attachment_ids as $attachment_id ) {
		if ( 'attachment' === get_post_type( $attachment_id ) ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}
}

// Reused (pre-existing) terms are kept, so surviving posts keep their terms.
$seeded_term_ids = get_option( 'wmpgf_seeded_term_ids' );
if ( is_array( $seeded_term_ids ) ) {
	foreach ( $seeded_term_ids as $term_id ) {
		$term_id = (int) $term_id;
		foreach ( array( WMPGF_Post_Type::TAX_CATEGORY, WMPGF_Post_Type::TAX_TAG ) as $taxonomy_name ) {
			$found_term = get_term( $term_id, $taxonomy_name );
			if ( $found_term && ! is_wp_error( $found_term ) ) {
				wp_delete_term( $term_id, $taxonomy_name );
				break;
			}
		}
	}
}

delete_option( 'wmpgf_seeded' );
delete_option( 'wmpgf_demo_page_id' );
delete_option( 'wmpgf_demo_page_owned' );
delete_option( 'wmpgf_seeded_post_ids' );
delete_option( 'wmpgf_seeded_attachment_ids' );
delete_option( 'wmpgf_seeded_term_ids' );
delete_option( 'wmpgf_rewrite_version' );
delete_option( 'wmpgf_category_children' ); // Created by core for hierarchical taxonomies.
