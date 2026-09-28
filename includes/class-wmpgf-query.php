<?php
/**
 * Builds the grid's queries. Results are cached for the request, so the
 * grid and the pagination block share one count query.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Query
 */
class WMPGF_Query {

	/**
	 * Matches the editor's RangeControl. Re-checked server-side because the
	 * attribute lives in post_content, and -1 would mean "every post".
	 */
	const MIN_POSTS_PER_PAGE = 1;
	const MAX_POSTS_PER_PAGE = 24;

	/**
	 * Total pages, keyed by query args.
	 *
	 * @var array<string, int>
	 */
	private static $total_pages = array();

	/**
	 * Builds a tax_query with the same semantics the REST API uses: OR
	 * within a taxonomy, AND across them. include_children => false matches
	 * the REST default, so nested categories filter the same on both paths.
	 *
	 * @param int[] $category_ids Selected wmpgf_category term IDs.
	 * @param int[] $tag_ids      Selected wmpgf_tag term IDs.
	 * @return array Empty if no filters are selected.
	 */
	public static function tax_query( $category_ids, $tag_ids ) {
		$tax_query = array();

		if ( $category_ids ) {
			$tax_query[] = array(
				'taxonomy'         => WMPGF_Post_Type::TAX_CATEGORY,
				'field'            => 'term_id',
				'terms'            => $category_ids,
				'include_children' => false,
			);
		}

		if ( $tag_ids ) {
			$tax_query[] = array(
				'taxonomy'         => WMPGF_Post_Type::TAX_TAG,
				'field'            => 'term_id',
				'terms'            => $tag_ids,
				'include_children' => false,
			);
		}

		return $tax_query;
	}

	/**
	 * Total pages for the given filters. A separate query because a
	 * WP_Query run past its last page reports max_num_pages as 0, leaving
	 * nothing to clamp against.
	 *
	 * @param int   $posts_per_page Posts per page.
	 * @param array $tax_query      Result of tax_query(), or empty.
	 * @return int At least 1.
	 */
	public static function total_pages( $posts_per_page, $tax_query ) {
		$query_args = array(
			'post_type'      => WMPGF_Post_Type::POST_TYPE,
			'posts_per_page' => $posts_per_page,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$key = md5( wp_json_encode( $query_args ) );
		if ( ! isset( self::$total_pages[ $key ] ) ) {
			self::$total_pages[ $key ] = max( 1, (int) ( new WP_Query( $query_args ) )->max_num_pages );
		}

		return self::$total_pages[ $key ];
	}

	/**
	 * Clamps an out-of-range ?wmpgf-page= (e.g. a stale bookmark) to the last page.
	 *
	 * @param int $requested_page As returned by WMPGF_Request::page().
	 * @param int $total_pages    As returned by total_pages().
	 * @return int
	 */
	public static function clamp_page( $requested_page, $total_pages ) {
		return min( $requested_page, $total_pages );
	}

	/**
	 * Clamps the postsPerPage attribute to the allowed range.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return int
	 */
	public static function sanitize_posts_per_page( $value ) {
		return min( self::MAX_POSTS_PER_PAGE, max( self::MIN_POSTS_PER_PAGE, absint( $value ) ) );
	}
}
