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
