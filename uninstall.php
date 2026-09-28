<?php
/**
 * Uninstall routine.
 *
 * Runs only when the plugin is deleted from the Plugins screen (not on a
 * plain deactivate), so it is the right place to remove everything the
 * seeder actually created: the demo page (if this plugin created it, not
 * merely adopted an existing one at that slug), the pgf_post entries and
 * featured images the seeder itself inserted, the taxonomy terms it itself
 * inserted, and the plugin's own options. Ownership -- not "matches this
 * post type/taxonomy" -- is what decides what gets deleted throughout this
 * file, so content a site owner created or already had in place is never
 * touched just because it happens to share a post type, taxonomy, or slug
 * with something the seeder made.
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
$pgf_post_type = new PGF_Post_Type();
$pgf_post_type->register_post_type();
$pgf_post_type->register_taxonomies();

/*
 * Deleted only if this plugin actually created it (pgf_demo_page_owned).
 * create_demo_page() adopts an already-existing page at the demo slug
 * rather than creating a duplicate, for idempotency -- but a page it
 * merely adopted is not one it owns, and deleting someone else's existing
 * page just because it happened to occupy that slug would be exactly the
 * kind of non-owned deletion this file's post/attachment cleanup below was
 * already fixed to avoid.
 */
$demo_page_id = (int) get_option( 'pgf_demo_page_id' );
if ( $demo_page_id && get_option( 'pgf_demo_page_owned' ) ) {
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

/*
 * Only terms this plugin actually inserted are deleted -- not every term
 * in these taxonomies. pgf_category/pgf_tag are dedicated to this plugin's
 * own post type, but create_terms()/create_tags() reuse (via term_exists())
 * rather than duplicate a term that already has the same name, for the
 * same idempotency reason posts and the demo page are adopted rather than
 * recreated -- a reused term was not created by this plugin. Beyond that,
 * deleting a term wholesale also strips its relationship from any
 * *surviving* post (a hand-created pgf_post kept because it wasn't in
 * pgf_seeded_post_ids, say) that happens to use it, silently losing that
 * post's category/tag even though the post itself is left alone.
 */
$seeded_term_ids = get_option( 'pgf_seeded_term_ids' );
if ( is_array( $seeded_term_ids ) ) {
	foreach ( $seeded_term_ids as $term_id ) {
		$term_id = (int) $term_id;
		// A given ID only ever belongs to whichever one of these two
		// taxonomies it was actually inserted into; checking both is just
		// how that's found back out, not a sign it could be ambiguous.
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
