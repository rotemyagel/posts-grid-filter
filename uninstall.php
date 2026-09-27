<?php
/**
 * Uninstall routine.
 *
 * Runs only when the plugin is deleted from the Plugins screen (not on a
 * plain deactivate), so it is the right place to remove everything the
 * seeder created: the demo page, all seeded pgf_post entries, their
 * featured images, the taxonomy terms, and the plugin's own options.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-pgf-post-type.php';

/*
 * uninstall.php runs with the plugin deactivated: its normal init-hooked
 * bootstrap (PGF_Post_Type::init(), called from the main plugin file) never
 * executes for this request, so the post type and taxonomies are not
 * registered here by default -- same reasoning as PGF_Plugin::activate(),
 * which registers them explicitly for the same reason.
 *
 * This matters differently for posts than for terms: WP_Query/get_posts()
 * match post_type by raw string against the database and work regardless
 * of registration, but get_terms() and wp_delete_term() both require the
 * taxonomy to be registered and return a WP_Error otherwise -- silently,
 * since the only symptom is that terms are never actually deleted. Caught
 * by actually running this file end-to-end (deactivate, invoke uninstall.php
 * exactly as WP core would, inspect the database directly) rather than by
 * reading the code: posts were correctly removed, but all 10 taxonomy terms
 * were left behind untouched.
 */
$post_type = new PGF_Post_Type();
$post_type->register_post_type();
$post_type->register_taxonomies();

$demo_page_id = (int) get_option( 'pgf_demo_page_id' );
if ( $demo_page_id ) {
	wp_delete_post( $demo_page_id, true );
}

/*
 * Only the specific posts/attachments recorded at seed time are deleted --
 * not every pgf_post in the database. pgf_post is a public, registered
 * custom post type; once seeded, creating one by hand through wp-admin is
 * normal, expected use of it, not a leftover to clean up. The previous
 * version of this file queried *all* pgf_post posts and deleted whatever
 * featured image each one currently had, which would delete a site owner's
 * own content (and possibly an existing, still-in-use media library image
 * they'd picked as that post's thumbnail) right along with the demo data.
 * A site with no seeded-ID record at all (e.g. upgrading from a version of
 * this plugin that predates this tracking) is left alone rather than
 * guessed at.
 */
$seeded_post_ids       = get_option( 'pgf_seeded_post_ids' );
$seeded_attachment_ids = get_option( 'pgf_seeded_attachment_ids' );

if ( is_array( $seeded_post_ids ) ) {
	foreach ( $seeded_post_ids as $post_id ) {
		if ( PGF_Post_Type::POST_TYPE === get_post_type( $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
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

/*
 * Terms are still removed wholesale, unlike posts/attachments: pgf_category
 * and pgf_tag are dedicated taxonomies this plugin registers for its own
 * post type alone (not a shared one like core's post_tag), so there is no
 * equivalent "a site owner made their own term and it would be wrongly
 * deleted" risk the way there is for posts and media.
 */
foreach ( array( PGF_Post_Type::TAX_CATEGORY, PGF_Post_Type::TAX_TAG ) as $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}
}

delete_option( 'pgf_seeded' );
delete_option( 'pgf_demo_page_id' );
delete_option( 'pgf_seeded_post_ids' );
delete_option( 'pgf_seeded_attachment_ids' );
delete_option( 'pgf_rewrite_version' );
