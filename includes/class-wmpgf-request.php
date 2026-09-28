<?php
/**
 * Reads and sanitizes the plugin's URL parameters, in one place.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Request
 */
class WMPGF_Request {

	/**
	 * Plain GET params rather than rewrite rules, so they work under any
	 * permalink structure. Hyphenated so they never equal a WordPress query
	 * var (the taxonomies' own query vars use underscores).
	 */
	const PAGE_PARAM     = 'wmpgf-page';
	const CATEGORY_PARAM = 'wmpgf-category';
	const TAG_PARAM      = 'wmpgf-tag';

	/**
	 * Term ID => slug per taxonomy, cached for the request.
	 *
	 * @var array<string, array<int, string>>
	 */
	private static $slug_maps = array();

	/**
	 * Current grid page from the URL.
	 *
	 * @return int
	 */
	public static function page() {
		if ( ! isset( $_GET[ self::PAGE_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 1;
		}

		return max( 1, absint( wp_unslash( $_GET[ self::PAGE_PARAM ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Term IDs for the slugs in ?wmpgf-category=design,culture (our links) or
	 * ?wmpgf-category[]=design&wmpgf-category[]=culture (the no-JS form), in the
	 * order given. Unknown slugs are ignored.
	 *
	 * @param string $param One of self::CATEGORY_PARAM / self::TAG_PARAM.
	 * @return int[]
	 */
	public static function term_ids( $param ) {
		if ( ! isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array();
		}

		// Sanitized by sanitize_title below.
		$raw   = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$slugs = is_array( $raw ) ? $raw : explode( ',', $raw );
		$slugs = array_map( 'sanitize_title', array_filter( $slugs, 'is_string' ) );
		$map   = self::term_slug_map()[ $param ];

		$ids = array();
		foreach ( array_unique( $slugs ) as $slug ) {
			$id = array_search( $slug, $map, true );
			if ( false !== $id ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}

	/**
	 * Term ID => slug for both taxonomies, keyed by URL param.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function term_slug_map() {
		$map = array();
		foreach ( array( self::CATEGORY_PARAM, self::TAG_PARAM ) as $param ) {
			$taxonomy = self::taxonomy_for( $param );
			if ( ! isset( self::$slug_maps[ $taxonomy ] ) ) {
				$terms                        = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'fields'     => 'id=>slug',
						'hide_empty' => false,
					)
				);
				self::$slug_maps[ $taxonomy ] = is_wp_error( $terms ) ? array() : $terms;
			}
			$map[ $param ] = self::$slug_maps[ $taxonomy ];
		}

		return $map;
	}

	/**
	 * Readable URL for the current page with a given page and selection,
	 * e.g. ?wmpgf-category=design,culture&wmpgf-page=2. Mirrors
	 * buildFilterUrl() in src/shared/filter-url.js.
	 *
	 * @param int   $page         Page number; 1 omits the page param.
	 * @param int[] $category_ids Selected wmpgf_category term IDs.
	 * @param int[] $tag_ids      Selected wmpgf_tag term IDs.
	 * @return string Unescaped URL.
	 */
	public static function page_url( $page, $category_ids, $tag_ids ) {
		$map    = self::term_slug_map();
		$params = array();
		foreach ( array(
			self::CATEGORY_PARAM => $category_ids,
			self::TAG_PARAM      => $tag_ids,
		) as $param => $ids ) {
			$slugs = array();
			foreach ( $ids as $id ) {
				if ( isset( $map[ $param ][ $id ] ) ) {
					$slugs[] = $map[ $param ][ $id ];
				}
			}
			if ( $slugs ) {
				$params[] = $param . '=' . implode( ',', $slugs );
			}
		}
		if ( $page > 1 ) {
			$params[] = self::PAGE_PARAM . '=' . (int) $page;
		}

		$base = remove_query_arg( array( self::CATEGORY_PARAM, self::TAG_PARAM, self::PAGE_PARAM ) );
		if ( ! $params ) {
			return $base;
		}

		return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . implode( '&', $params );
	}

	/**
	 * Taxonomy behind a filter URL param.
	 *
	 * @param string $param One of self::CATEGORY_PARAM / self::TAG_PARAM.
	 * @return string
	 */
	private static function taxonomy_for( $param ) {
		return self::TAG_PARAM === $param ? WMPGF_Post_Type::TAX_TAG : WMPGF_Post_Type::TAX_CATEGORY;
	}
}
