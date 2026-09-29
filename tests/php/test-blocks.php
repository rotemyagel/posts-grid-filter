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

	public function test_card_titles_are_h2_unless_h3_or_h4_is_chosen() {
		self::factory()->post->create( array( 'post_type' => WMPGF_Post_Type::POST_TYPE ) );
		// Each card title's opening and closing tag, as "h2/h2".
		$tag = static function ( $attributes ) {
			$html = do_blocks( '<!-- wp:wmpgf/posts-grid ' . $attributes . ' --><!-- wp:wmpgf/pagination /--><!-- /wp:wmpgf/posts-grid -->' );
			preg_match_all( '/<(h\d) class="wmpgf-grid__title">.*?<\/(h\d)>/s', $html, $titles );
			$pairs = array_map(
				static function ( $open, $close ) {
					return "$open/$close";
				},
				$titles[1],
				$titles[2]
			);
			return array_values( array_unique( $pairs ) );
		};

		$this->assertSame( array( 'h2/h2' ), $tag( '{}' ) );
		$this->assertSame( array( 'h3/h3' ), $tag( '{"titleLevel":3}' ) );
		$this->assertSame( array( 'h4/h4' ), $tag( '{"titleLevel":4}' ) );
		// Only h2 to h4: anything else is h2.
		$this->assertSame( array( 'h2/h2' ), $tag( '{"titleLevel":1}' ) );
		$this->assertSame( array( 'h2/h2' ), $tag( '{"titleLevel":6}' ) );
		$this->assertSame( array( 'h2/h2' ), $tag( '{"titleLevel":"script"}' ) );
	}

	public function test_the_tokens_are_inlined_instead_of_a_render_blocking_link() {
		$styles   = wp_styles();
		$original = clone $styles->registered['wmpgf-tokens'];

		wp_enqueue_style( 'wmpgf-tokens' );
		wp_maybe_inline_styles();
		$registered = $styles->registered['wmpgf-tokens'];
		$inline     = implode( '', (array) $styles->get_data( 'wmpgf-tokens', 'after' ) );

		$styles->registered['wmpgf-tokens'] = $original;
		wp_dequeue_style( 'wmpgf-tokens' );

		$this->assertFalse( $registered->src, 'No <link>: the file is printed inline.' );
		$this->assertStringContainsString( '--wmpgf-text-s', $inline );
		// Built like the blocks' stylesheets: minified, without comments.
		$this->assertStringNotContainsString( '/*', $inline );
		$this->assertLessThan( 3, substr_count( $inline, "\n" ) );
		// The font's relative URL is rewritten from the site root.
		$this->assertStringContainsString( 'url(/wp-content/plugins/', str_replace( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '', $inline ) );
		$this->assertStringNotContainsString( 'url(../fonts/', $inline );
		// A metric-matched stand-in keeps text in place when the font swaps in.
		$compact = str_replace( array( ' ', '"' ), '', $inline );
		$this->assertMatchesRegularExpression( '/@font-face\{[^}]*font-family:BricolageGrotesqueFallback;[^}]*size-adjust:106\.51%/', $compact );
		$this->assertStringContainsString( 'src:local(Arial)', $compact );
		$this->assertStringContainsString( '--wmpgf-font:BricolageGrotesque,BricolageGrotesqueFallback,system-ui', $compact );
	}
}
