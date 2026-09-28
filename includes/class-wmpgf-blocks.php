<?php
/**
 * Registers the plugin's blocks and their inserter category.
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
	 * Block-inserter category the plugin's blocks are grouped under.
	 */
	const BLOCK_CATEGORY_SLUG = 'wmpgf';

	/**
	 * Block directories under build/blocks/.
	 *
	 * @var string[]
	 */
	private $blocks = array( 'posts-grid', 'pagination', 'posts-filter' );

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
	 * Registers the shared design tokens, then every block from its
	 * build/blocks/<name>/block.json (each lists "wmpgf-tokens" as a style).
	 */
	public function register_blocks() {
		wp_register_style(
			'wmpgf-tokens',
			WMPGF_URL . 'assets/css/tokens.css',
			array(),
			(string) filemtime( WMPGF_DIR . 'assets/css/tokens.css' )
		);

		foreach ( $this->blocks as $block ) {
			$path = WMPGF_DIR . 'build/blocks/' . $block;

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type( $path );
		}
	}
}
