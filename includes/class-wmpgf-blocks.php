<?php
/**
 * Registers the plugin's blocks from their built block.json metadata.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Blocks
 */
class WMPGF_Blocks {

	/**
	 * Plain GET params rather than rewrite rules, so they work under any
	 * permalink structure without a flush.
	 */
	const PAGE_PARAM     = 'wmpgf-page';
	const CATEGORY_PARAM = 'wmpgf-category';
	const TAG_PARAM      = 'wmpgf-tag';

	/**
	 * Matches the editor's RangeControl. Re-checked server-side because the
	 * attribute lives in post_content, and -1 would mean "every post".
	 */
	const MIN_POSTS_PER_PAGE = 1;
	const MAX_POSTS_PER_PAGE = 24;

	/**
	 * Block directories under build/blocks/.
	 *
	 * @var string[]
	 */
	private $blocks = array( 'posts-grid', 'pagination', 'posts-filter' );

	/**
	 * Block-inserter category the plugin's blocks are grouped under.
	 */
	const BLOCK_CATEGORY_SLUG = 'wmpgf';

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
	}

	/**
	 * Adds the "WM Widgets" inserter category first in the list, so it shows
	 * near the top. No icon, matching core's own category headings.
	 *
	 * @param array $categories Existing block categories.
	 * @return array
	 */
	public function register_block_category( $categories ) {
		return array_merge(
			array(
				array(
					'slug'  => self::BLOCK_CATEGORY_SLUG,
					'title' => __( 'WM Widgets', 'wm-posts-grid-filter' ),
				),
			),
			$categories
		);
	}

	/**
	 * Current grid page from the URL, shared by the grid and pagination.
	 *
	 * @return int
	 */
	public static function get_requested_page() {
		if ( ! isset( $_GET[ self::PAGE_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 1;
		}

		return max( 1, absint( wp_unslash( $_GET[ self::PAGE_PARAM ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Term IDs for the slugs in ?wmpgf-category=design,culture (our links) or
	 * ?wmpgf-category[]=design&wmpgf-category[]=culture (the no-JS form), in the
	 * order given. Unknown slugs are ignored.
	 *
	 * @param string $param One of self::CATEGORY_PARAM / self::TAG_PARAM.
	 * @return int[]
	 */
	public static function get_requested_term_ids( $param ) {
		if ( ! isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array();
		}

		// Sanitized by sanitize_title below.
		$raw   = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$slugs = is_array( $raw ) ? $raw : explode( ',', $raw );
		$slugs = array_map( 'sanitize_title', array_filter( $slugs, 'is_string' ) );
		$map   = self::term_slug_map()[ $param ];

		$ids = array();
		foreach ( array_unique( $slugs ) as $slug ) {
			$id = array_search( $slug, $map, true );
			if ( false !== $id ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}

	/**
	 * Term ID => slug for both taxonomies, keyed by URL param. Passed to the
	 * browser so the address bar and pagination links can use slugs while
	 * the store and REST requests work with IDs.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function term_slug_map() {
		$map = array();
		foreach ( array( self::CATEGORY_PARAM, self::TAG_PARAM ) as $param ) {
			$terms         = get_terms(
				array(
					'taxonomy'   => self::taxonomy_for( $param ),
					'fields'     => 'id=>slug',
					'hide_empty' => false,
				)
			);
			$map[ $param ] = is_wp_error( $terms ) ? array() : $terms;
		}

		return $map;
	}

	/**
	 * Readable URL for the current page with a given page and selection,
	 * e.g. ?wmpgf-category=design,culture&wmpgf-page=2. Mirrors
	 * buildFilterUrl() in src/shared/filter-url.js.
	 *
	 * @param int   $page         Page number; 1 omits the page param.
	 * @param int[] $category_ids Selected wmpgf_category term IDs.
	 * @param int[] $tag_ids      Selected wmpgf_tag term IDs.
	 * @return string Unescaped URL.
	 */
	public static function page_url( $page, $category_ids, $tag_ids ) {
		$map    = self::term_slug_map();
		$params = array();
		foreach ( array(
			self::CATEGORY_PARAM => $category_ids,
			self::TAG_PARAM      => $tag_ids,
		) as $param => $ids ) {
			$slugs = array();
			foreach ( $ids as $id ) {
				if ( isset( $map[ $param ][ $id ] ) ) {
					$slugs[] = $map[ $param ][ $id ];
				}
			}
			if ( $slugs ) {
				$params[] = $param . '=' . implode( ',', $slugs );
			}
		}
		if ( $page > 1 ) {
			$params[] = self::PAGE_PARAM . '=' . (int) $page;
		}

		$base = remove_query_arg( array( self::CATEGORY_PARAM, self::TAG_PARAM, self::PAGE_PARAM ) );
		if ( ! $params ) {
			return $base;
		}

		return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . implode( '&', $params );
	}

	/**
	 * Taxonomy behind a filter URL param.
	 *
	 * @param string $param One of self::CATEGORY_PARAM / self::TAG_PARAM.
	 * @return string
	 */
	private static function taxonomy_for( $param ) {
		return self::TAG_PARAM === $param ? WMPGF_Post_Type::TAX_TAG : WMPGF_Post_Type::TAX_CATEGORY;
	}

	/**
	 * Builds a tax_query with the same semantics the REST API uses: OR
	 * within a taxonomy, AND across them. include_children => false matches
	 * the REST default, so nested categories filter the same on both paths.
	 *
	 * @param int[] $category_ids Selected wmpgf_category term IDs.
	 * @param int[] $tag_ids      Selected wmpgf_tag term IDs.
	 * @return array Empty if no filters are selected.
	 */
	public static function build_tax_query( $category_ids, $tag_ids ) {
		$tax_query = array();

		if ( $category_ids ) {
			$tax_query[] = array(
				'taxonomy'         => WMPGF_Post_Type::TAX_CATEGORY,
				'field'            => 'term_id',
				'terms'            => $category_ids,
				'include_children' => false,
			);
		}

		if ( $tag_ids ) {
			$tax_query[] = array(
				'taxonomy'         => WMPGF_Post_Type::TAX_TAG,
				'field'            => 'term_id',
				'terms'            => $tag_ids,
				'include_children' => false,
			);
		}

		return $tax_query;
	}

	/**
	 * Total pages for the given filters, shared by the grid and pagination.
	 * A separate query because a WP_Query run past its last page reports
	 * max_num_pages as 0, leaving nothing to clamp against.
	 *
	 * @param int   $posts_per_page Posts per page.
	 * @param array $tax_query      Result of build_tax_query(), or empty.
	 * @return int At least 1.
	 */
	public static function get_total_pages( $posts_per_page, $tax_query ) {
		$query_args = array(
			'post_type'      => WMPGF_Post_Type::POST_TYPE,
			'posts_per_page' => $posts_per_page,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		return max( 1, (int) ( new WP_Query( $query_args ) )->max_num_pages );
	}

	/**
	 * Clamps an out-of-range ?wmpgf-page= (e.g. a stale bookmark) to the last page.
	 *
	 * @param int $requested_page As returned by get_requested_page().
	 * @param int $total_pages    As returned by get_total_pages().
	 * @return int
	 */
	public static function clamp_page( $requested_page, $total_pages ) {
		return min( $requested_page, $total_pages );
	}

	/**
	 * Clamps the postsPerPage attribute to the allowed range.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return int
	 */
	public static function sanitize_posts_per_page( $value ) {
		$value = absint( $value );

		if ( $value < self::MIN_POSTS_PER_PAGE ) {
			return self::MIN_POSTS_PER_PAGE;
		}

		if ( $value > self::MAX_POSTS_PER_PAGE ) {
			return self::MAX_POSTS_PER_PAGE;
		}

		return $value;
	}

	/**
	 * Logs seeding failures, only when WP_DEBUG_LOG is enabled.
	 *
	 * @param string $message Message to log.
	 */
	public static function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Posts Grid + Filter] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Registers every block from its build/blocks/<name>/block.json.
	 */
	public function register_blocks() {
		foreach ( $this->blocks as $block ) {
			$path = WMPGF_DIR . 'build/blocks/' . $block;

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type( $path );
		}
	}
}
