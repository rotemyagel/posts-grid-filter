<?php
/**
 * Post type and taxonomy registration.
 *
 * @package WMPGF
 */

/**
 * The first category assigned is the post's primary one everywhere.
 */
class Test_WMPGF_Post_Type extends WMPGF_TestCase {

	public function test_rest_returns_categories_in_assignment_order() {
		$zulu  = self::factory()->term->create(
			array(
				'taxonomy' => WMPGF_Post_Type::TAX_CATEGORY,
				'name'     => 'Zulu',
			)
		);
		$alpha = self::factory()->term->create(
			array(
				'taxonomy' => WMPGF_Post_Type::TAX_CATEGORY,
				'name'     => 'Alpha',
			)
		);
		$post  = self::factory()->post->create( array( 'post_type' => WMPGF_Post_Type::POST_TYPE ) );
		// Zulu first, so alphabetical order would put it second.
		wp_set_object_terms( $post, array( $zulu, $alpha ), WMPGF_Post_Type::TAX_CATEGORY );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . WMPGF_Post_Type::POST_TYPE . '/' . $post ) );

		// The editor preview labels each card with the first of these.
		$this->assertSame( array( $zulu, $alpha ), $response->get_data()[ WMPGF_Post_Type::TAX_CATEGORY ] );
		$this->assertSame( 'Zulu', get_the_terms( $post, WMPGF_Post_Type::TAX_CATEGORY )[0]->name );
	}
}
