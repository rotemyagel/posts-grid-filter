<?php
/**
 * Registers the plugin's blocks from their built block.json metadata.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PGF_Blocks
 */
class PGF_Blocks {

	/**
	 * Query string key used for page-1-and-up pagination links. A plain GET
	 * parameter (rather than a registered rewrite/query var) so it works on
	 * any page regardless of permalink structure, with no rewrite flush.
	 */
	const PAGE_PARAM = 'pgf-page';

	/**
	 * Query string keys for the currently selected category/tag term IDs.
	 * Read directly from $_GET (same reasoning as PAGE_PARAM: no rewrite
	 * registration needed, works under any permalink structure) by every
	 * block that needs to filter or reflect the current filter selection --
	 * posts-grid (to filter its query), pagination (to filter its count
	 * query and to preserve the selection when a Prev/Next link causes a
	 * full page reload), and posts-filter (to pre-check the matching
	 * checkboxes on a direct/shared link, before any JS has run).
	 */
	const CATEGORY_PARAM = 'pgf_category';
	const TAG_PARAM      = 'pgf_tag';

	/**
	 * Bounds for the postsPerPage block attribute, matching the editor's own
	 * RangeControl (min=1, max=24). Enforced again here because block
	 * attributes are stored in post_content and can be edited directly
	 * (raw HTML/REST, a hand-edited import) without ever touching the
	 * RangeControl UI -- an untrusted value flows straight into WP_Query's
	 * posts_per_page otherwise, where -1 means "return every post".
	 */
	const MIN_POSTS_PER_PAGE = 1;
	const MAX_POSTS_PER_PAGE = 24;

	/**
	 * Block directories, relative to the build/ output, in registration
	 * order. Order does not matter functionally but keeps grid before its
	 * inner pagination block for readability.
	 *
	 * @var string[]
	 */
	private $blocks = array( 'posts-grid', 'pagination', 'posts-filter' );

	/**
	 * Hooks registration into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Reads the current grid page from the URL. Read by both posts-grid's
	 * render.php (so the correct page is server-rendered on direct load or
	 * a no-JS request) and pagination's render.php (so Prev/Next links are
	 * built from the same number), so the two can never disagree about
	 * which page is "current".
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
	 * Reads a list of term IDs from a $_GET[ $param ][] array parameter,
	 * e.g. ?pgf_category[]=8&pgf_category[]=10, sanitizing every value to a
	 * positive integer and dropping anything that isn't one.
	 *
	 * @param string $param One of self::CATEGORY_PARAM / self::TAG_PARAM.
	 * @return int[]
	 */
	public static function get_requested_term_ids( $param ) {
		if ( ! isset( $_GET[ $param ] ) || ! is_array( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array();
		}

		$ids = array_map( 'absint', wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Builds a WP_Query tax_query array from selected category/tag term IDs:
	 * OR within a taxonomy (multiple terms in one clause), AND across
	 * taxonomies (multiple clauses) -- the same semantics the REST API
	 * applies automatically for the client-side filtered fetch, kept
	 * consistent here for the server-rendered/no-JS path.
	 *
	 * 'include_children' is set explicitly to false, matching
	 * WP_REST_Posts_Controller's own default for a plain array-of-term-IDs
	 * taxonomy query var (verified directly against WordPress core source --
	 * it defaults there to false, not WP_Tax_Query's own default of true).
	 * The seeded pgf_category terms are flat, so this has no visible effect
	 * on the demo content, but without it, a real site that later nests
	 * pgf_category terms would get different filtered results server-side
	 * (a page-2 reload, or any no-JS request) than the AJAX-filtered REST
	 * path gives for the exact same selection.
	 *
	 * @param int[] $category_ids Selected pgf_category term IDs.
	 * @param int[] $tag_ids      Selected pgf_tag term IDs.
	 * @return array Empty if no filters are selected.
	 */
	public static function build_tax_query( $category_ids, $tag_ids ) {
		$tax_query = array();

		if ( $category_ids ) {
			$tax_query[] = array(
				'taxonomy'         => PGF_Post_Type::TAX_CATEGORY,
				'field'            => 'term_id',
				'terms'            => $category_ids,
				'include_children' => false,
			);
		}

		if ( $tag_ids ) {
			$tax_query[] = array(
				'taxonomy'         => PGF_Post_Type::TAX_TAG,
				'field'            => 'term_id',
				'terms'            => $tag_ids,
				'include_children' => false,
			);
		}

		return $tax_query;
	}

	/**
	 * Total page count for the given filters, from a dedicated `fields =>
	 * ids` query with no `paged` value of its own. Shared by posts-grid's
	 * render.php (to clamp an out-of-range ?pgf-page= before running its
	 * main query) and pagination's render.php (to render "Page X of Y" and
	 * disable Prev/Next correctly) specifically so the two can never
	 * disagree about how many pages exist. This has to come from a
	 * *separate* query rather than reusing the main content query's own
	 * `max_num_pages` afterward -- verified directly that a WP_Query already
	 * run with `paged` set far beyond the real last page comes back with
	 * `found_posts`/`max_num_pages` both 0, not the true total, so there is
	 * nothing reliable to clamp against from inside that same query once it
	 * has already executed with an out-of-range offset.
	 *
	 * @param int   $posts_per_page Posts per page.
	 * @param array $tax_query      Result of build_tax_query(), or empty.
	 * @return int At least 1.
	 */
	public static function get_total_pages( $posts_per_page, $tax_query ) {
		$query_args = array(
			'post_type'      => PGF_Post_Type::POST_TYPE,
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
	 * Clamps a requested page number to the last real page, so an
	 * out-of-range ?pgf-page= (a hand-edited URL, a stale bookmark) renders
	 * real content and a matching "Page X of Y" instead of an empty grid
	 * next to a label reporting the page that was actually asked for.
	 *
	 * @param int $requested_page As returned by get_requested_page().
	 * @param int $total_pages    As returned by get_total_pages().
	 * @return int
	 */
	public static function clamp_page( $requested_page, $total_pages ) {
		return min( $requested_page, $total_pages );
	}

	/**
	 * Clamps a postsPerPage attribute value to a sane range. Used by both
	 * posts-grid's render.php (the main query) and pagination's render.php
	 * (its own count query), so the two can never disagree about page size.
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
	 * Logs a message via PHP's error log, but only when WP_DEBUG_LOG is
	 * enabled -- the standard WordPress convention for diagnostic logging
	 * that stays silent on production sites that never opted into it.
	 * Used for failure paths (a seed post/term/image that couldn't be
	 * created) that would otherwise fail completely silently, making a
	 * partially-seeded install ("why do I only have 8 of 12 posts?")
	 * impossible to diagnose after the fact.
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
			$path = PGF_DIR . 'build/blocks/' . $block;

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type( $path );
		}
	}
}
