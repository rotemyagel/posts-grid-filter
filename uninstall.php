<?php
/**
 * Uninstall routine (runs when the plugin is deleted, not on deactivate).
 *
 * Deletes only what the seeder recorded as created by this plugin, never
 * anything that merely shares its post type, taxonomy, or slug.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-pgf-post-type.php';

// The plugin is inactive here, and get_terms()/wp_delete_term() fail on
// unregistered taxonomies, so register them for this request.
$pgf_post_type = new PGF_Post_Type();
$pgf_post_type->register_post_type();
$pgf_post_type->register_taxonomies();

// An adopted, pre-existing page at the demo slug is not ours to delete.
$demo_page_id = (int) get_option( 'pgf_demo_page_id' );
if ( $demo_page_id && get_option( 'pgf_demo_page_owned' ) ) {
	wp_delete_post( $demo_page_id, true );
}

// Only the recorded IDs: hand-made pgf_posts and their images are kept.
$seeded_post_ids       = get_option( 'pgf_seeded_post_ids' );
$seeded_attachment_ids = get_option( 'pgf_seeded_attachment_ids' );

if ( is_array( $seeded_post_ids ) ) {
	foreach ( $seeded_post_ids as $seeded_post_id ) {
		if ( PGF_Post_Type::POST_TYPE === get_post_type( $seeded_post_id ) ) {
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
$seeded_term_ids = get_option( 'pgf_seeded_term_ids' );
if ( is_array( $seeded_term_ids ) ) {
	foreach ( $seeded_term_ids as $term_id ) {
		$term_id = (int) $term_id;
		foreach ( array( PGF_Post_Type::TAX_CATEGORY, PGF_Post_Type::TAX_TAG ) as $taxonomy_name ) {
			$found_term = get_term( $term_id, $taxonomy_name );
			if ( $found_term && ! is_wp_error( $found_term ) ) {
				wp_delete_term( $term_id, $taxonomy_name );
				break;
			}
		}
	}
}

delete_option( 'pgf_seeded' );
delete_option( 'pgf_demo_page_id' );
delete_option( 'pgf_demo_page_owned' );
delete_option( 'pgf_seeded_post_ids' );
delete_option( 'pgf_seeded_attachment_ids' );
delete_option( 'pgf_seeded_term_ids' );
delete_option( 'pgf_rewrite_version' );
