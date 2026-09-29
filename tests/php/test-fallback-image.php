<?php
/**
 * Cards whose post has no usable featured image.
 *
 * @package WMPGF
 */

/**
 * A fallback image when one is set, otherwise a placeholder the size of a
 * cover, so a row of cards always lines up.
 */
class Test_WMPGF_Fallback_Image extends WMPGF_TestCase {

	public function set_up() {
		parent::set_up();
		wp_increase_content_media_count( - wp_increase_content_media_count( 0 ) );
		wp_high_priority_element_flag( true );
	}

	/**
	 * An image attachment with dimensions, no file needed.
	 *
	 * @param string $name File name.
	 * @return int Attachment ID.
	 */
	private function image( $name ) {
		$id = self::factory()->attachment->create_object( $name, 0, array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 300,
				'height' => 200,
				'file'   => $name,
			)
		);
		return $id;
	}

	/**
	 * A grid post, created a minute apart so the grid order is known
	 * (newest first).
	 *
	 * @param int $minutes_ago Age of the post.
	 * @param int $image       Featured image ID, or 0 for none.
	 * @return int Post ID.
	 */
	private function post( $minutes_ago, $image = 0 ) {
		$post = self::factory()->post->create(
			array(
				'post_type' => WMPGF_Post_Type::POST_TYPE,
				'post_date' => gmdate( 'Y-m-d H:i:s', time() - 60 * $minutes_ago ),
			)
		);
		if ( $image ) {
			set_post_thumbnail( $post, $image );
		}
		return $post;
	}

	/**
	 * Each card's image area, in order: placeholder, or the image's file name.
	 *
	 * @param string $attributes Grid block attributes as JSON.
	 * @param bool   $as_content Render through the_content, inside a page's loop.
	 * @return string[]
	 */
	private function thumbs( $attributes = '{}', $as_content = false ) {
		$grid = "<!-- wp:wmpgf/posts-grid $attributes --><!-- wp:wmpgf/pagination /--><!-- /wp:wmpgf/posts-grid -->";
		if ( $as_content ) {
			$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
			$GLOBALS['wp_query']->the_post();
			$html = apply_filters( 'the_content', $grid ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook, applied as WordPress does.
		} else {
			$html = do_blocks( $grid );
		}

		preg_match_all( '/<article class="wmpgf-grid__card">(.*?)<\/article>/s', $html, $cards );
		return array_map(
			static function ( $card ) {
				if ( false !== strpos( $card, 'wmpgf-grid__thumb--placeholder' ) ) {
					return 'placeholder';
				}
				if ( ! preg_match( '/<img [^>]*>/', $card, $img ) ) {
					return 'nothing';
				}
				preg_match( '/src="[^"]*\/([^"\/]+)"/', $img[0], $src );
				return $src[1] . ( false !== strpos( $img[0], 'fetchpriority="high"' ) ? ' (high)' : '' );
			},
			$cards[1]
		);
	}

	public function test_a_post_without_an_image_gets_a_placeholder() {
		$this->post( 2, $this->image( 'a.jpg' ) );
		$this->post( 1 );

		$this->assertSame( array( 'placeholder', 'a.jpg' ), $this->thumbs() );
	}

	public function test_the_fallback_image_replaces_the_placeholder_only() {
		$fallback = $this->image( 'fallback.jpg' );
		$this->post( 2, $this->image( 'a.jpg' ) );
		$this->post( 1 );

		$this->assertSame(
			array( 'fallback.jpg', 'a.jpg' ),
			$this->thumbs( wp_json_encode( array( 'fallbackImageId' => $fallback ) ) )
		);
	}

	public function test_a_featured_image_that_is_not_a_usable_image_counts_as_none() {
		// Deleting an image through WordPress also clears the posts using it,
		// but these cases keep has_post_thumbnail() true: a featured "image"
		// that is a PDF, and an ID left behind by a database edit or import.
		$pdf = self::factory()->attachment->create_object( 'doc.pdf', 0, array( 'post_mime_type' => 'application/pdf' ) );
		$this->post( 2, $pdf );
		$orphan = $this->post( 1 );
		update_post_meta( $orphan, '_thumbnail_id', 999999 );

		$this->assertSame( array( 'placeholder', 'placeholder' ), $this->thumbs() );
	}

	public function test_a_fallback_id_that_is_not_an_image_is_ignored() {
		$this->post( 1 );
		$not_an_image = self::factory()->post->create();

		$this->assertSame(
			array( 'placeholder' ),
			$this->thumbs( wp_json_encode( array( 'fallbackImageId' => $not_an_image ) ) )
		);
	}

	public function test_the_first_card_with_an_image_anchors_image_loading() {
		$this->post( 3, $this->image( 'b.jpg' ) );
		$this->post( 2, $this->image( 'a.jpg' ) );
		$this->post( 1 );

		// The placeholder isn't an image: the first real one is high priority.
		$this->assertSame( array( 'placeholder', 'a.jpg (high)', 'b.jpg' ), $this->thumbs( '{}', true ) );
	}
}
