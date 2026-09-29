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
	 * The visitor's light/dark choice: an attribute on <html> that the
	 * blocks' CSS reads, and the localStorage key that remembers it. Without
	 * a saved choice the blocks follow the device (prefers-color-scheme).
	 */
	const COLOR_SCHEME_ATTRIBUTE   = 'data-wmpgf-color-scheme';
	const COLOR_SCHEME_STORAGE_KEY = 'wmpgf-color-scheme';

	/**
	 * Block directories under build/blocks/.
	 *
	 * @var string[]
	 */
	private $blocks = array( 'posts-grid', 'pagination', 'posts-filter' );

	/**
	 * Whether this request has printed the color scheme script.
	 *
	 * @var bool
	 */
	private static $color_scheme_script_printed = false;

	/**
	 * The attribute and storage key, for the filter's script.
	 *
	 * @return array<string, string>
	 */
	public static function color_scheme_config() {
		return array(
			'attribute'  => self::COLOR_SCHEME_ATTRIBUTE,
			'storageKey' => self::COLOR_SCHEME_STORAGE_KEY,
		);
	}

	/**
	 * Applies a saved light/dark choice before the blocks paint, so a
	 * visitor who chose dark never sees them flash light first. Printed
	 * once, inside the first of the plugin's blocks on the page; it runs
	 * as the browser parses it, before the markup that follows.
	 */
	public static function print_color_scheme_script() {
		if ( self::$color_scheme_script_printed ) {
			return;
		}
		self::$color_scheme_script_printed = true;

		wp_print_inline_script_tag(
			sprintf(
				'try{var s=localStorage.getItem(%1$s);if(s==="light"||s==="dark"){document.documentElement.setAttribute(%2$s,s);}}catch(e){}',
				wp_json_encode( self::COLOR_SCHEME_STORAGE_KEY ),
				wp_json_encode( self::COLOR_SCHEME_ATTRIBUTE )
			)
		);
	}

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
	 * Registers the shared design tokens (built from src/tokens.css), then
	 * every block from its build/blocks/<name>/block.json (each lists
	 * "wmpgf-tokens" as a style).
	 */
	public function register_blocks() {
		$tokens = 'build/tokens.css';
		wp_register_style(
			'wmpgf-tokens',
			WMPGF_URL . $tokens,
			array(),
			(string) filemtime( WMPGF_DIR . $tokens )
		);
		// With a path, core can print them inline with the blocks' own
		// styles instead of as a render-blocking <link>.
		wp_style_add_data( 'wmpgf-tokens', 'path', WMPGF_DIR . $tokens );

		foreach ( $this->blocks as $block ) {
			$path = WMPGF_DIR . 'build/blocks/' . $block;

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type( $path );
		}
	}
}
