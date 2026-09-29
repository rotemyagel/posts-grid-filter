<?php
/**
 * Which card images load right away, which early at low priority, and
 * which lazily. Rendered the way real pages are: through the_content, or
 * straight from a template.
 *
 * @package WMPGF
 */

/**
 * The grid follows the page's decision for its first image.
 */
class Test_WMPGF_Lazy_Loading extends WMPGF_TestCase {

	/**
	 * Cover image URLs of the 8 posts.
	 *
	 * @var string[]
	 */
	private $covers = array();

	public function set_up() {
		parent::set_up();

		// Core keeps its count of the page's content images, and whether
		// fetchpriority="high" was used, in statics; reset both.
		wp_increase_content_media_count( - wp_increase_content_media_count( 0 ) );
		wp_high_priority_element_flag( true );

		for ( $i = 0; $i < 8; $i++ ) {
			$post  = self::factory()->post->create( array( 'post_type' => WMPGF_Post_Type::POST_TYPE ) );
			$image = self::factory()->attachment->create_object(
				"cover-$i.jpg",
				$post,
				array( 'post_mime_type' => 'image/jpeg' )
			);
			wp_update_attachment_metadata(
				$image,
				array(
					'width'  => 300,
					'height' => 200,
					'file'   => "cover-$i.jpg",
				)
			);
			set_post_thumbnail( $post, $image );
		}

		// Render inside a page's main loop, as a real request does.
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$GLOBALS['wp_query']->the_post();
	}

	/**
	 * A grid block's markup.
	 *
	 * @param int $columns Grid columns.
	 * @return string
	 */
	private function grid( $columns ) {
		return sprintf( '<!-- wp:wmpgf/posts-grid {"columns":%d,"postsPerPage":8} --><!-- wp:wmpgf/pagination /--><!-- /wp:wmpgf/posts-grid -->', $columns );
	}

	/**
	 * Content as WordPress renders a page's content.
	 *
	 * @param string $content Block markup.
	 * @return string
	 */
	private function content( $content ) {
		return apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook, applied as WordPress does.
	}

	/**
	 * Each card image's loading, in order: high (eager, fetchpriority
	 * high), eager, low (eager, fetchpriority low) or lazy.
	 *
	 * @param string $html Rendered page content.
	 * @return string[]
	 */
	private function loading( $html ) {
		preg_match_all( '/<img [^>]*' . WMPGF_Image_Loading::ATTRIBUTE . '[^>]*>|<img [^>]*cover-\d[^>]*>/', $html, $images );

		return array_map(
			static function ( $img ) {
				$priority = preg_match( '/fetchpriority="(\w+)"/', $img, $match ) ? $match[1] : '';
				if ( false !== strpos( $img, 'loading="lazy"' ) ) {
					return $priority ? "lazy+$priority" : 'lazy';
				}
				return $priority ? $priority : 'eager';
			},
			$images[0]
		);
	}

	public function test_three_columns_at_the_top_of_the_content() {
		$this->assertSame(
			array( 'high', 'eager', 'eager', 'low', 'low', 'low', 'lazy', 'lazy' ),
			$this->loading( $this->content( $this->grid( 3 ) ) )
		);
	}

	public function test_a_four_column_first_row_loads_right_away() {
		// The page's optimiser alone makes the 4th image lazy though it's in view.
		$this->assertSame(
			array( 'high', 'eager', 'eager', 'eager', 'low', 'low', 'low', 'low' ),
			$this->loading( $this->content( $this->grid( 4 ) ) )
		);
	}

	public function test_a_grid_further_down_the_page_stays_lazy() {
		$above = '';
		foreach ( array( 'a', 'b', 'c' ) as $name ) {
			$above .= "<p><img src=\"http://example.org/$name.jpg\" width=\"1200\" height=\"800\" alt=\"\" /></p>";
		}

		$this->assertSame(
			array_fill( 0, 8, 'lazy' ),
			$this->loading( $this->content( $above . $this->grid( 4 ) ) )
		);
	}

	public function test_outside_post_content_the_same_rules_apply() {
		// A grid placed straight in a template: core decides as each image is built.
		$this->assertSame(
			array( 'high', 'eager', 'eager', 'eager', 'low', 'low', 'low', 'low' ),
			$this->loading( do_blocks( $this->grid( 4 ) ) )
		);
	}

	public function test_it_follows_a_plugin_that_replaces_core_image_loading() {
		// Like Elementor's optimised image loading: core's lazy loading off,
		// and a content filter that makes every image after the first lazy.
		// Core's fetchpriority="high" logic still runs here.
		add_filter( 'wp_lazy_loading_enabled', '__return_false' );
		$seen      = 0;
		$optimiser = static function ( $image ) use ( &$seen ) {
			return $seen++ ? str_replace( '<img', '<img loading="lazy"', $image ) : $image;
		};
		add_filter( 'wp_content_img_tag', $optimiser, 10 );

		$loading = $this->loading( $this->content( $this->grid( 3 ) ) );

		remove_filter( 'wp_content_img_tag', $optimiser, 10 );
		remove_filter( 'wp_lazy_loading_enabled', '__return_false' );
		$this->assertSame( array( 'high', 'eager', 'eager', 'low', 'low', 'low', 'lazy', 'lazy' ), $loading );
	}
}
