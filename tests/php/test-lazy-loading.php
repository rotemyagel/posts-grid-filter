<?php
/**
 * Which card images load right away and which lazily.
 *
 * @package WMPGF
 */

/**
 * The grid follows core's decision, extended to the whole first row.
 */
class Test_WMPGF_Lazy_Loading extends WMPGF_TestCase {

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
	 * Each card image's loading, in order: high (eager, fetchpriority
	 * high), eager, or lazy.
	 *
	 * @param int $columns Grid columns.
	 * @return string[]
	 */
	private function loading( $columns ) {
		$html = do_blocks( sprintf( '<!-- wp:wmpgf/posts-grid {"columns":%d,"postsPerPage":8} --><!-- wp:wmpgf/pagination /--><!-- /wp:wmpgf/posts-grid -->', $columns ) );
		preg_match_all( '/<img [^>]*>/', $html, $images );

		return array_map(
			static function ( $img ) {
				if ( false !== strpos( $img, 'loading="lazy"' ) ) {
					return 'lazy';
				}
				return false !== strpos( $img, 'fetchpriority="high"' ) ? 'high' : 'eager';
			},
			$images[0]
		);
	}

	public function test_three_columns_keep_core_behaviour() {
		$this->assertSame(
			array( 'high', 'eager', 'eager', 'lazy', 'lazy', 'lazy', 'lazy', 'lazy' ),
			$this->loading( 3 )
		);
	}

	public function test_a_four_column_first_row_loads_right_away() {
		// Core alone would make the 4th image lazy though it's in the first row.
		$this->assertSame(
			array( 'high', 'eager', 'eager', 'eager', 'lazy', 'lazy', 'lazy', 'lazy' ),
			$this->loading( 4 )
		);
	}

	public function test_a_grid_further_down_the_page_stays_lazy() {
		// Three content images before the grid: core lazy-loads from its first.
		wp_increase_content_media_count( 3 );

		$this->assertSame( array_fill( 0, 8, 'lazy' ), $this->loading( 4 ) );
	}
}
