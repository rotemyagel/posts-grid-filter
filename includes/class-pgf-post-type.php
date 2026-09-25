<?php
/**
 * Registers the custom post type and taxonomies that back the demo content.
 *
 * A dedicated post type + taxonomies (rather than core post/category/tag)
 * keeps the assessment's demo content fully isolated from whatever real
 * content already exists on the install the plugin is activated on.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PGF_Post_Type
 */
class PGF_Post_Type {

	const POST_TYPE   = 'pgf_post';
	const TAX_CATEGORY = 'pgf_category';
	const TAX_TAG       = 'pgf_tag';

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Registers the pgf_post custom post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Grid Posts', 'posts-grid-filter' ),
				'labels'       => array(
					'name'          => __( 'Grid Posts', 'posts-grid-filter' ),
					'singular_name' => __( 'Grid Post', 'posts-grid-filter' ),
					'add_new_item'  => __( 'Add New Grid Post', 'posts-grid-filter' ),
					'edit_item'     => __( 'Edit Grid Post', 'posts-grid-filter' ),
					'search_items'  => __( 'Search Grid Posts', 'posts-grid-filter' ),
					'not_found'     => __( 'No grid posts found', 'posts-grid-filter' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => self::POST_TYPE,
				'menu_icon'    => 'dashicons-grid-view',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'grid-posts' ),
			)
		);
	}

	/**
	 * Registers the pgf_category (hierarchical) and pgf_tag (flat) taxonomies.
	 */
	public function register_taxonomies() {
		register_taxonomy(
			self::TAX_CATEGORY,
			array( self::POST_TYPE ),
			array(
				'label'             => __( 'Grid Categories', 'posts-grid-filter' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => self::TAX_CATEGORY,
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			self::TAX_TAG,
			array( self::POST_TYPE ),
			array(
				'label'             => __( 'Grid Tags', 'posts-grid-filter' ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => self::TAX_TAG,
				'show_admin_column' => true,
			)
		);
	}
}
