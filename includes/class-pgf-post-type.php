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
	 *
	 * register_taxonomies() is deliberately hooked before register_post_type()
	 * (both otherwise default priority 10, so registration order here is
	 * registration order in the compiled rewrite rules too): pgf_category's
	 * own archive rule (prefixed with the literal "pgf_category/") and the
	 * pgf_post permastruct (a bare %pgf_category%/%pgf_post% pattern with no
	 * literal prefix, so it can match ANY two path segments, including
	 * "pgf_category/some-term/") both need to run before WordPress compiles
	 * its final rule list, and whichever is compiled first wins when a URL
	 * matches both. Verified directly against the generated rewrite_rules
	 * option after swapping this order: with post_type registered first,
	 * /pgf_category/business/ was wrongly swallowed by the CPT's generic
	 * two-segment pattern before the taxonomy's own, more specific rule ever
	 * got a chance.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_rewrite_tag' ), 5 );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_to_canonical' ) );
	}

	/**
	 * Registers the %pgf_category% rewrite tag used by register_post_type()'s
	 * own rewrite slug below. Must run before rewrite rules are generated
	 * (any time before a flush is fine -- both this and register_post_type()
	 * only accumulate state that gets compiled together at flush time), and
	 * on every request, since WordPress's rewrite tag registry is rebuilt
	 * fresh each time rather than persisted. The 'pgf_category=' query var
	 * this maps to is the taxonomy's own default query var (see
	 * register_taxonomies() below), so a single-post URL's category segment
	 * becomes a real tax_query constraint alongside the post name -- the
	 * same mechanism WooCommerce uses for its own %product_cat%/%postname%
	 * permalink option.
	 */
	public function register_rewrite_tag() {
		add_rewrite_tag( '%pgf_category%', '([^/]+)', self::TAX_CATEGORY . '=' );
	}

	/**
	 * Registers the pgf_post custom post type. The rewrite slug is a
	 * %pgf_category% placeholder rather than a fixed string, so permalinks
	 * are e.g. /product-news/some-post/ instead of /grid-posts/some-post/ --
	 * filter_permalink() below fills in the actual category slug per post,
	 * the same way WordPress core fills in %category% for the built-in
	 * `post` type, which register_post_type() has no equivalent built-in
	 * support for on a custom post type.
	 */
	public function register_post_type() {
		/*
		 * 'walk_dirs' => false matters here, not just style: WordPress's
		 * rewrite generator normally also emits progressively shorter
		 * prefix-only variants of a permastruct (the same mechanism that
		 * makes /2020/06/ work as a standalone archive alongside the full
		 * /2020/06/15/post-name/ date structure). With a bare %pgf_category%
		 * placeholder and no literal text in front of it, that shorter
		 * variant comes out as a completely unprefixed ([^/]+)/?$ rule --
		 * verified directly against the generated rewrite_rules option,
		 * where it was silently swallowing every top-level page on the
		 * site, including this plugin's own demo page, ahead of
		 * WordPress's actual page-matching rule. 'walk_dirs' => false
		 * suppresses that shorter variant, leaving only the intended
		 * two-segment structure.
		 */
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
	 * Replaces the %pgf_category% placeholder left in a pgf_post permalink
	 * by register_post_type()'s own rewrite slug with the post's actual
	 * category slug -- the lowest term ID among however many categories are
	 * assigned, mirroring exactly which one WordPress core picks for a
	 * regular post's %category% tag. Falls back to a literal 'uncategorized'
	 * segment (never an empty one, which would collapse two slashes
	 * together into a malformed URL) for the rare case of a pgf_post saved
	 * with no category at all -- normal seeded content always has one, but
	 * nothing stops a post being created by hand without it.
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
	 * Redirects a pgf_post request to its canonical URL when the category
	 * segment doesn't match. Needed because, unlike core's own %category%
	 * permalink tag on the built-in `post` type, WordPress's redirect_canonical()
	 * does not generalize this correction to a custom rewrite tag on a custom
	 * post type: verified directly that /wrong-category/real-post-slug/ (and
	 * even a made-up, non-existent category segment) resolved the right post
	 * with a 200 and no redirect, rather than either 404ing or self-correcting.
	 * The post's <link rel="canonical"> already points to the right URL
	 * regardless (WordPress's default rel_canonical() uses get_permalink(),
	 * which already runs through filter_permalink() above), so this isn't
	 * fixing broken content -- it's closing a duplicate-URL gap that would
	 * otherwise let the same post live at unlimited category-prefixed URLs.
	 */
	public function redirect_to_canonical() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		$canonical = get_permalink( get_queried_object_id() );
		if ( ! $canonical ) {
			return;
		}

		$canonical_path = (string) wp_parse_url( $canonical, PHP_URL_PATH );
		$requested_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( untrailingslashit( $requested_path ) === untrailingslashit( $canonical_path ) ) {
			return;
		}

		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
