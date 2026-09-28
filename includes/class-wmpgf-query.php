<?php
/**
 * Builds the grid's queries. Results are cached for the request, so the
 * filter, the grid and the pagination block share one count.
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
	 * Matching-post counts, keyed by filters.
	 *
	 * @var array<string, int>
	 */
	private static $counts = array();

	/**
	 * Page results, keyed by filters, page size and page.
	 *
	 * @var array<string, array>
	 */
	private static $pages = array();

	/**
	 * Number of posts matching the filters.
	 *
	 * @param array $filters As returned by WMPGF_Request::filters().
	 * @return int
	 */
	public static function count( array $filters ) {
		$key = md5( wp_json_encode( $filters ) );

		if ( ! isset( self::$counts[ $key ] ) ) {
			$query                = new WP_Query(
				self::base_args( $filters ) + array(
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			self::$counts[ $key ] = (int) $query->found_posts;
		}

		return self::$counts[ $key ];
	}

	/**
	 * One page of matching posts. An out-of-range page (e.g. a stale
	 * bookmark) is clamped to the last page, which is why the count runs
	 * first: WP_Query reports no totals once it's past the last page.
	 *
	 * @param array $filters        As returned by WMPGF_Request::filters().
	 * @param int   $posts_per_page Posts per page.
	 * @param int   $requested_page As returned by WMPGF_Request::page().
	 * @return array{query: WP_Query, page: int, total_pages: int, total: int}
	 */
	public static function page( array $filters, $posts_per_page, $requested_page ) {
		$posts_per_page = self::sanitize_posts_per_page( $posts_per_page );
		$total          = self::count( $filters );
		$total_pages    = max( 1, (int) ceil( $total / $posts_per_page ) );
		$page           = min( max( 1, (int) $requested_page ), $total_pages );
		$key            = md5( wp_json_encode( array( $filters, $posts_per_page, $page ) ) );

		if ( ! isset( self::$pages[ $key ] ) ) {
			$query = new WP_Query(
				self::base_args( $filters ) + array(
					'posts_per_page' => $posts_per_page,
					'paged'          => $page,
					'no_found_rows'  => true,
				)
			);
			// One query for all featured images instead of two per card.
			update_post_thumbnail_cache( $query );

			self::$pages[ $key ] = array(
				'query'       => $query,
				'page'        => $page,
				'total_pages' => $total_pages,
				'total'       => $total,
			);
		}

		return self::$pages[ $key ];
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

	/**
	 * Query args shared by the count and the page queries.
	 *
	 * @param array $filters As returned by WMPGF_Request::filters().
	 * @return array
	 */
	private static function base_args( array $filters ) {
		$args = array(
			'post_type'   => WMPGF_Post_Type::POST_TYPE,
			'post_status' => 'publish',
		);

		$tax_query = self::tax_query( $filters['categories'], $filters['tags'] );
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		if ( '' !== $filters['search'] ) {
			$args['s'] = $filters['search'];
		}

		return $args;
	}

	/**
	 * OR within a taxonomy (several terms in one clause), AND across
	 * taxonomies (several clauses).
	 *
	 * @param string[] $category_slugs Selected wmpgf_category slugs.
	 * @param string[] $tag_slugs      Selected wmpgf_tag slugs.
	 * @return array Empty if nothing is selected.
	 */
	private static function tax_query( array $category_slugs, array $tag_slugs ) {
		$tax_query = array();

		foreach ( array(
			WMPGF_Post_Type::TAX_CATEGORY => $category_slugs,
			WMPGF_Post_Type::TAX_TAG      => $tag_slugs,
		) as $taxonomy => $slugs ) {
			if ( $slugs ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}

		return $tax_query;
	}
}
