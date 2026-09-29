<?php
/**
 * Paging through posts that share a date.
 *
 * @package WMPGF
 */

/**
 * The demo posts are all created in the same second. Sorted by date alone,
 * the database may order them differently for each page, so a visitor
 * paging through could see a post twice and never see another.
 */
class Test_WMPGF_Stable_Order extends WMPGF_TestCase {

	/**
	 * Seven Grid Posts with the same date, newest ID first.
	 *
	 * @return int[]
	 */
	private function same_second_posts() {
		$ids = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_type' => WMPGF_Post_Type::POST_TYPE,
					'post_date' => '2026-01-01 12:00:00',
				)
			);
		}
		rsort( $ids );
		return $ids;
	}

	public function test_the_grid_shows_each_post_once_newest_id_first() {
		$expected = $this->same_second_posts();

		$seen = array();
		for ( $page = 1; $page <= 4; $page++ ) {
			$result = WMPGF_Query::page( WMPGF_Request::filters(), 2, $page );
			$seen   = array_merge( $seen, wp_list_pluck( $result['query']->posts, 'ID' ) );
		}

		$this->assertSame( $expected, $seen );
		$this->assertStringContainsString( 'post_date DESC, ' . $GLOBALS['wpdb']->posts . '.ID DESC', $result['query']->request );
	}

	public function test_the_editor_preview_reads_them_in_the_same_order() {
		$expected = $this->same_second_posts();

		$sql    = array();
		$record = static function ( $request ) use ( &$sql ) {
			$sql[] = $request;
			return $request;
		};
		add_filter( 'posts_request', $record );

		$seen = array();
		for ( $page = 1; $page <= 4; $page++ ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/' . WMPGF_Post_Type::POST_TYPE );
			$request->set_query_params(
				array(
					'per_page' => 2,
					'page'     => $page,
				)
			);
			$seen = array_merge( $seen, wp_list_pluck( rest_get_server()->dispatch( $request )->get_data(), 'id' ) );
		}
		remove_filter( 'posts_request', $record );

		$this->assertSame( $expected, $seen );
		// The database may happen to break ties the same way; the SQL says it must.
		$this->assertStringContainsString( 'post_date DESC, ' . $GLOBALS['wpdb']->posts . '.ID DESC', implode( "\n", $sql ) );
	}

	public function test_other_queries_are_left_alone() {
		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertStringNotContainsString( '.ID DESC', $query->request );
	}
}
