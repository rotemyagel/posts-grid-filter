<?php
/**
 * The meta description on single Grid Post pages.
 *
 * @package WMPGF
 */

/**
 * One description, from the excerpt, only on the plugin's own post type,
 * and none when something else on the page already prints one.
 */
class Test_WMPGF_Meta_Description extends WMPGF_TestCase {

	/**
	 * What wp_head prints.
	 *
	 * @return string
	 */
	private function head() {
		ob_start();
		do_action( 'wp_head' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook.
		return ob_get_clean();
	}

	/**
	 * Visits a new Grid Post.
	 *
	 * @param array $args Post fields.
	 */
	private function visit_grid_post( array $args ) {
		$post_id = self::factory()->post->create( array_merge( array( 'post_type' => WMPGF_Post_Type::POST_TYPE ), $args ) );
		$this->go_to( get_permalink( $post_id ) );
	}

	/**
	 * The content of each meta description in some HTML.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private function descriptions( $html ) {
		preg_match_all( '/<meta name="description" content="([^"]*)"/', $html, $matches );
		return $matches[1];
	}

	public function test_a_grid_post_is_described_by_its_excerpt() {
		$this->visit_grid_post(
			array(
				'post_excerpt' => "Why <em>tokens</em> named for their role\n survive a rebrand & more.",
				'post_content' => 'The body.',
			)
		);

		$this->assertSame( array( 'Why tokens named for their role survive a rebrand &amp; more.' ), $this->descriptions( $this->head() ) );
	}

	public function test_without_an_excerpt_the_content_is_cut_at_a_word() {
		$this->visit_grid_post(
			array(
				'post_excerpt' => '',
				'post_content' => '<!-- wp:paragraph --><p>' . str_repeat( 'Seventeen ', 30 ) . '</p><!-- /wp:paragraph -->',
			)
		);

		$description = $this->descriptions( $this->head() )[0];
		$this->assertLessThanOrEqual( WMPGF_Meta_Description::MAX_LENGTH, mb_strlen( $description ) );
		$this->assertMatchesRegularExpression( '/^(Seventeen )+Seventeen…$/u', $description );
	}

	public function test_none_when_the_page_already_has_one() {
		$this->visit_grid_post( array( 'post_excerpt' => 'Our excerpt.' ) );
		$seo_plugin = static function () {
			echo '<meta name="description" content="From an SEO plugin" />' . "\n";
		};
		add_action( 'wp_head', $seo_plugin, 1 );

		$descriptions = $this->descriptions( $this->head() );
		remove_action( 'wp_head', $seo_plugin, 1 );

		$this->assertSame( array( 'From an SEO plugin' ), $descriptions );
	}

	public function test_other_pages_are_left_to_the_theme_and_seo_plugins() {
		$post_id = self::factory()->post->create( array( 'post_excerpt' => 'A normal post.' ) );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame( array(), $this->descriptions( $this->head() ) );
	}

	public function test_a_buffer_left_open_by_another_plugin_is_not_taken() {
		$this->visit_grid_post( array( 'post_excerpt' => 'Our excerpt.' ) );
		$level        = ob_get_level();
		$other_plugin = static function () {
			ob_start();
		};
		add_action( 'wp_head', $other_plugin, 5 );

		$head = $this->head();
		remove_action( 'wp_head', $other_plugin, 5 );
		// Ours and head()'s own are still open: the other plugin's buffer
		// was the one head() closed.
		$this->assertSame( $level + 2, ob_get_level() );
		$head = ob_get_clean() . ob_get_clean() . $head;

		$this->assertSame( array(), $this->descriptions( $head ) );
	}
}
