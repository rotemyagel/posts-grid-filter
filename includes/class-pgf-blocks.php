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
