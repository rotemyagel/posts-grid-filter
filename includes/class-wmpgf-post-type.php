<?php
/**
 * Registers the wmpgf_post post type and its taxonomies, kept separate from
 * core post/category/tag so demo content never mixes with a site's own.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Post_Type
 */
class WMPGF_Post_Type {

	const POST_TYPE    = 'wmpgf_post';
	const TAX_CATEGORY = 'wmpgf_category';
	const TAX_TAG      = 'wmpgf_tag';

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers wmpgf_post. Single posts live under a fixed base
	 * (/grid-post/{slug}/), so the rule can never match another URL.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Grid Posts', 'wm-posts-grid-filter' ),
				'labels'       => array(
					'name'          => __( 'Grid Posts', 'wm-posts-grid-filter' ),
					'singular_name' => __( 'Grid Post', 'wm-posts-grid-filter' ),
					'add_new_item'  => __( 'Add New Grid Post', 'wm-posts-grid-filter' ),
					'edit_item'     => __( 'Edit Grid Post', 'wm-posts-grid-filter' ),
					'search_items'  => __( 'Search Grid Posts', 'wm-posts-grid-filter' ),
					'not_found'     => __( 'No grid posts found', 'wm-posts-grid-filter' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => self::POST_TYPE,
				'menu_icon'    => 'dashicons-grid-view',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'has_archive'  => false,
				'rewrite'      => array(
					'slug'       => 'grid-post',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Registers the wmpgf_category (hierarchical) and wmpgf_tag (flat) taxonomies.
	 */
	public function register_taxonomies() {
		register_taxonomy(
			self::TAX_CATEGORY,
			array( self::POST_TYPE ),
			array(
				'label'             => __( 'Grid Categories', 'wm-posts-grid-filter' ),
				'hierarchical'      => true,
				// Keeps the order categories were assigned in; the first is the
				// post's primary category (shown on its card).
				'sort'              => true,
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
				'label'             => __( 'Grid Tags', 'wm-posts-grid-filter' ),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => self::TAX_TAG,
				'show_admin_column' => true,
			)
		);
	}
}
