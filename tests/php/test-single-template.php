<?php
/**
 * The single Grid Post page on block and classic themes.
 *
 * @package WMPGF
 */

/**
 * Block themes have no header.php, so the PHP template's get_header() fell
 * back to a deprecated file: a notice in the page, no doctype, no theme
 * header. A block theme gets a block template instead.
 */
class Test_WMPGF_Single_Template extends WMPGF_TestCase {

	/**
	 * Theme active before the test.
	 *
	 * @var string
	 */
	private $original_theme;

	public function set_up() {
		parent::set_up();
		$this->original_theme = get_stylesheet();
	}

	public function tear_down() {
		if ( WP_Block_Templates_Registry::get_instance()->is_registered( WMPGF_Single_Template::BLOCK_TEMPLATE ) ) {
			unregister_block_template( WMPGF_Single_Template::BLOCK_TEMPLATE );
		}
		remove_theme_support( 'block-templates' );
		remove_theme_support( 'block-template-parts' );
		switch_theme( $this->original_theme );
		parent::tear_down();
	}

	/**
	 * Switches to Twenty Twenty-Five as WordPress would, then lets the
	 * plugin register its template, as it does on init.
	 */
	private function use_block_theme() {
		if ( ! wp_get_theme( 'twentytwentyfive' )->exists() ) {
			$this->markTestSkipped( 'Twenty Twenty-Five is not installed.' );
		}
		switch_theme( 'twentytwentyfive' );
		// Added on after_setup_theme for block themes, which has already run.
		add_theme_support( 'block-templates' );
		add_theme_support( 'block-template-parts' );
		( new WMPGF_Single_Template() )->register_block_template();
	}

	/**
	 * A Grid Post with a category and a tag, and the request for it.
	 *
	 * @return int Post ID.
	 */
	private function visit_grid_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => WMPGF_Post_Type::POST_TYPE,
				'post_title'   => 'A grid post',
				'post_content' => '<!-- wp:paragraph --><p>The body text.</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_object_terms( $post_id, 'Design', WMPGF_Post_Type::TAX_CATEGORY );
		wp_set_object_terms( $post_id, 'Guide', WMPGF_Post_Type::TAX_TAG );
		$this->go_to( get_permalink( $post_id ) );
		return $post_id;
	}

	/**
	 * The template WordPress's template loader would include.
	 *
	 * @return string
	 */
	private function template() {
		return apply_filters( 'template_include', get_single_template() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook, applied as template-loader.php does.
	}

	public function test_a_block_theme_renders_a_full_page_with_its_own_header_and_footer() {
		$this->use_block_theme();
		$this->visit_grid_post();

		$deprecated_files = array();
		$record           = static function ( $file ) use ( &$deprecated_files ) {
			$deprecated_files[] = $file;
		};
		add_action( 'deprecated_file_included', $record );
		$log = wp_tempnam( 'wmpgf-debug-log' );
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Captures what WP_DEBUG_LOG would write, for this include only.
		$previous_log = ini_set( 'error_log', $log );

		$template = $this->template();
		ob_start();
		require $template;
		$html = ob_get_clean();

		ini_set( 'error_log', $previous_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		remove_action( 'deprecated_file_included', $record );
		$logged = file_get_contents( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local temp file.
		unlink( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertSame( ABSPATH . WPINC . '/template-canvas.php', $template );
		$this->assertStringStartsWith( '<!DOCTYPE html>', ltrim( $html ) );
		$this->assertMatchesRegularExpression( '/<meta name="viewport"[^>]*width=device-width/', $html );
		$this->assertMatchesRegularExpression( '/<header[^>]*wp-block-template-part/', $html );
		$this->assertMatchesRegularExpression( '/<footer[^>]*wp-block-template-part/', $html );
		$this->assertMatchesRegularExpression( '/<h1[^>]*wp-block-post-title[^>]*>A grid post<\/h1>/', $html );
		$this->assertStringContainsString( '>Design</a>', $html );
		$this->assertStringContainsString( '>Guide</a>', $html );
		$this->assertStringContainsString( 'The body text.', $html );
		$this->assertSame( array(), $deprecated_files );
		$this->assertSame( '', $logged, 'Nothing was written to the debug log.' );
	}

	public function test_a_classic_theme_keeps_the_php_template_and_its_stylesheet() {
		$this->assertFalse( wp_is_block_theme() );
		( new WMPGF_Single_Template() )->register_block_template();
		$this->visit_grid_post();

		$this->assertFalse( WP_Block_Templates_Registry::get_instance()->is_registered( WMPGF_Single_Template::BLOCK_TEMPLATE ) );
		$this->assertSame( WMPGF_DIR . 'templates/single-wmpgf_post.php', $this->template() );

		( new WMPGF_Single_Template() )->enqueue_assets();
		$this->assertTrue( wp_style_is( 'wmpgf-single', 'enqueued' ) );
		wp_dequeue_style( 'wmpgf-single' );
	}

	public function test_a_block_theme_does_not_load_the_classic_stylesheet() {
		$this->use_block_theme();
		$this->visit_grid_post();

		( new WMPGF_Single_Template() )->enqueue_assets();
		$this->assertFalse( wp_style_is( 'wmpgf-single', 'enqueued' ) );
	}
}
