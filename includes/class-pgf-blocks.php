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
