<?php
/**
 * Server-rendered block output.
 *
 * @package WMPGF
 */

/**
 * The light/dark switch and its no-flash script.
 */
class Test_WMPGF_Blocks extends WMPGF_TestCase {

	const PAGE = '<!-- wp:wmpgf/posts-filter /--><!-- wp:wmpgf/posts-grid --><!-- wp:wmpgf/pagination /--><!-- /wp:wmpgf/posts-grid -->';

	public function test_the_saved_scheme_script_is_printed_once_before_the_blocks() {
		$html = do_blocks( self::PAGE );

		$this->assertSame( 1, substr_count( $html, 'localStorage.getItem("' . WMPGF_Blocks::COLOR_SCHEME_STORAGE_KEY . '")' ) );
		// Before any block markup, so it runs before they paint.
		$this->assertLessThan( strpos( $html, 'wmpgf-filter__form' ), strpos( $html, 'localStorage' ) );
	}

	public function test_the_switch_starts_hidden_for_browsers_without_javascript() {
		$html = do_blocks( self::PAGE );

		$this->assertMatchesRegularExpression( '/<button[^>]*class="wmpgf-filter__scheme"[^>]*\shidden[\s>]/', $html );
		$this->assertStringContainsString( 'data-wp-bind--hidden="!state.colorScheme"', $html );
	}

	public function test_the_switch_can_be_turned_off() {
		$html = do_blocks( '<!-- wp:wmpgf/posts-filter {"showColorSchemeToggle":false} /-->' );

		$this->assertStringNotContainsString( 'wmpgf-filter__scheme', $html );
		// The blocks still follow a saved choice or the device.
		$this->assertStringContainsString( 'localStorage.getItem', $html );
	}
}
