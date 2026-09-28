<?php
/**
 * Registers the pgf_post post type and its taxonomies, kept separate from
 * core post/category/tag so demo content never mixes with a site's own.
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

	const POST_TYPE    = 'pgf_post';
	const TAX_CATEGORY = 'pgf_category';
	const TAX_TAG      = 'pgf_tag';

	/**
	 * Hooks registration into WordPress.
	 *
	 * Taxonomies must register before the post type: the post's bare
	 * %pgf_category%/%postname% rule matches any two path segments, so if it
	 * compiled first it would swallow /pgf_category/{term}/ archive URLs.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_rewrite_tag' ), 5 );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_to_canonical' ) );
	}

	/**
	 * Registers the %pgf_category% permalink tag, mapped to the taxonomy's
	 * query var (the same approach WooCommerce uses for %product_cat%).
	 */
	public function register_rewrite_tag() {
		add_rewrite_tag( '%pgf_category%', '([^/]+)', self::TAX_CATEGORY . '=' );
	}

	/**
	 * Registers pgf_post with /{category}/{post}/ permalinks; core has no
	 * built-in %category% support for custom post types.
	 */
	public function register_post_type() {
		// walk_dirs => false: otherwise WordPress also emits a bare ([^/]+)/?$
		// rule for the placeholder, which swallows every top-level page.
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
					'slug'       => '%pgf_category%',
					'with_front' => false,
					'walk_dirs'  => false,
				),
			)
		);
	}

	/**
	 * Fills in %pgf_category% with the post's lowest-ID category, the same
	 * choice core makes for %category%, or 'uncategorized' if it has none.
	 *
	 * @param string  $post_link Permalink still containing the placeholder.
	 * @param WP_Post $post      Post object.
	 * @return string
	 */
	public function filter_permalink( $post_link, $post ) {
		if ( self::POST_TYPE !== $post->post_type || false === strpos( $post_link, '%pgf_category%' ) ) {
			return $post_link;
		}

		$terms = get_the_terms( $post, self::TAX_CATEGORY );
		$slug  = 'uncategorized';

		if ( $terms && ! is_wp_error( $terms ) ) {
			usort(
				$terms,
				static function ( $a, $b ) {
					return $a->term_id - $b->term_id;
				}
			);
			$slug = $terms[0]->slug;
		}

		return str_replace( '%pgf_category%', $slug, $post_link );
	}

	/**
	 * 301s /wrong-category/post/ to the canonical URL. Core's
	 * redirect_canonical() doesn't do this for custom rewrite tags, so any
	 * category segment would otherwise serve the post.
	 */
	public function redirect_to_canonical() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		$canonical = get_permalink( get_queried_object_id() );
		if ( ! $canonical ) {
			return;
		}

		// Neither value is output: the path is only compared, and the query
		// string goes to wp_safe_redirect(), which validates the host.
		$canonical_path = (string) wp_parse_url( $canonical, PHP_URL_PATH );
		$requested_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( untrailingslashit( $requested_path ) === untrailingslashit( $canonical_path ) ) {
			return;
		}

		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirect_to  = $query_string ? $canonical . '?' . $query_string : $canonical;

		wp_safe_redirect( $redirect_to, 301 );
		exit;
	}

	/**
	 * Registers the pgf_category (hierarchical) and pgf_tag (flat) taxonomies.
	 */
	public function register_taxonomies() {
		register_taxonomy(
			self::TAX_CATEGORY,
			array( self::POST_TYPE ),
			array(
				'label'             => __( 'Grid Categories', 'wm-posts-grid-filter' ),
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
