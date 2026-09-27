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

$post_ids = get_posts(
	array(
		'post_type'      => PGF_Post_Type::POST_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $post_ids as $post_id ) {
	$thumbnail_id = get_post_thumbnail_id( $post_id );
	if ( $thumbnail_id ) {
		wp_delete_attachment( $thumbnail_id, true );
	}
	wp_delete_post( $post_id, true );
}

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
delete_option( 'pgf_db_version' );
