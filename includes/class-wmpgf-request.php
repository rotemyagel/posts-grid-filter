<?php
/**
 * Reads and sanitizes the plugin's URL parameters, and builds URLs from
 * them. The only place that touches $_GET.
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
	const SEARCH_PARAM   = 'wmpgf-search';
	const PER_PAGE_PARAM = 'wmpgf-per-page';

	/**
	 * Page sizes offered to visitors (the block's own default is added too).
	 */
	const PER_PAGE_OPTIONS = array( 6, 12, 24 );

	/**
	 * Longest search term kept; anything longer is cut.
	 */
	const MAX_SEARCH_LENGTH = 100;

	/**
	 * Existing term slugs per taxonomy, cached for the request.
	 *
	 * @var array<string, string[]>
	 */
	private static $known_slugs = array();

	/**
	 * URL param names, for the browser (wp_interactivity_config).
	 *
	 * @return array<string, string>
	 */
	public static function params() {
		return array(
			'page'       => self::PAGE_PARAM,
			'categories' => self::CATEGORY_PARAM,
			'tags'       => self::TAG_PARAM,
			'search'     => self::SEARCH_PARAM,
			'perPage'    => self::PER_PAGE_PARAM,
		);
	}

	/**
	 * Posts per page: the visitor's choice from ?wmpgf-per-page= if present,
	 * otherwise the block's attribute, clamped to the allowed range either
	 * way. The grid and the pagination block both call this, so they always
	 * agree on the page size.
	 *
	 * @param mixed $block_default The block's postsPerPage attribute.
	 * @return int
	 */
	public static function per_page( $block_default ) {
		$requested = isset( $_GET[ self::PER_PAGE_PARAM ] ) && is_string( $_GET[ self::PER_PAGE_PARAM ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? (int) $_GET[ self::PER_PAGE_PARAM ] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 0;

		// Missing, zero, negative or not a number: use the block's setting.
		return WMPGF_Query::sanitize_posts_per_page( $requested >= 1 ? $requested : $block_default );
	}

	/**
	 * Current grid page from the URL.
	 *
	 * @return int
	 */
	public static function page() {
		if ( ! isset( $_GET[ self::PAGE_PARAM ] ) || ! is_string( $_GET[ self::PAGE_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 1;
		}

		// (int), not absint(): absint() would turn -3 into page 3.
		return max( 1, (int) $_GET[ self::PAGE_PARAM ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The current filter selection. Search, categories and tags combine
	 * with AND.
	 *
	 * @return array{categories: string[], tags: string[], search: string}
	 */
	public static function filters() {
		return array(
			'categories' => self::slugs( self::CATEGORY_PARAM, WMPGF_Post_Type::TAX_CATEGORY ),
			'tags'       => self::slugs( self::TAG_PARAM, WMPGF_Post_Type::TAX_TAG ),
			'search'     => self::search(),
		);
	}

	/**
	 * Search term from ?wmpgf-search=, as plain text.
	 *
	 * @return string Empty if none.
	 */
	private static function search() {
		if ( ! isset( $_GET[ self::SEARCH_PARAM ] ) || ! is_string( $_GET[ self::SEARCH_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		$search = sanitize_text_field( wp_unslash( $_GET[ self::SEARCH_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return trim( mb_substr( $search, 0, self::MAX_SEARCH_LENGTH ) );
	}

	/**
	 * Term slugs from ?wmpgf-category=design,culture (our links) or
	 * ?wmpgf-category[]=design&wmpgf-category[]=culture (the no-JS form), in
	 * the order given. Slugs that aren't existing terms are dropped.
	 *
	 * @param string $param    URL param.
	 * @param string $taxonomy Taxonomy the slugs belong to.
	 * @return string[]
	 */
	private static function slugs( $param, $taxonomy ) {
		if ( ! isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array();
		}

		// Sanitized by sanitize_title below.
		$raw   = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$slugs = is_array( $raw ) ? $raw : explode( ',', $raw );
		$slugs = array_unique( array_map( 'sanitize_title', array_filter( $slugs, 'is_string' ) ) );

		if ( ! isset( self::$known_slugs[ $taxonomy ] ) ) {
			$known                          = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'fields'     => 'slugs',
					'hide_empty' => false,
				)
			);
			self::$known_slugs[ $taxonomy ] = is_wp_error( $known ) ? array() : $known;
		}

		return array_values( array_intersect( $slugs, self::$known_slugs[ $taxonomy ] ) );
	}

	/**
	 * Readable URL of the current page with some params replaced, e.g.
	 * ?wmpgf-category=design,culture&wmpgf-page=2. Params not named in
	 * $changes keep their current value; the page resets to 1 unless given.
	 * Mirrors urlWith() in src/shared/url.js.
	 *
	 * @param array $changes Keys of params(): 'categories' and 'tags' take
	 *                       slug arrays, 'search' a string, 'perPage' and
	 *                       'page' ints.
	 * @return string Unescaped URL.
	 */
	public static function url( array $changes ) {
		$names    = self::params();
		$replaced = array( self::PAGE_PARAM );
		foreach ( array_keys( $changes ) as $key ) {
			$replaced[] = $names[ $key ];
		}

		// The raw query string is filtered, not re-built, so unrelated params
		// keep their exact encoding (remove_query_arg() would turn , into %2C).
		// Output is escaped with esc_url() by the caller.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parts       = explode( '?', $request_uri, 2 );
		// One leading slash, so the result can never become a //host link.
		$path  = '/' . ltrim( $parts[0], '/' );
		$query = array();
		foreach ( explode( '&', $parts[1] ?? '' ) as $part ) {
			if ( '' !== $part && ! self::is_param( strtok( $part, '=' ), $replaced ) ) {
				$query[] = $part;
			}
		}

		foreach ( array( 'categories', 'tags' ) as $key ) {
			if ( ! empty( $changes[ $key ] ) ) {
				$query[] = $names[ $key ] . '=' . implode( ',', array_map( 'sanitize_title', $changes[ $key ] ) );
			}
		}
		// Free text, so it is the one value that must be encoded.
		if ( isset( $changes['search'] ) && '' !== $changes['search'] ) {
			$query[] = self::SEARCH_PARAM . '=' . rawurlencode( $changes['search'] );
		}
		if ( ! empty( $changes['perPage'] ) ) {
			$query[] = self::PER_PAGE_PARAM . '=' . (int) $changes['perPage'];
		}
		if ( isset( $changes['page'] ) && $changes['page'] > 1 ) {
			$query[] = self::PAGE_PARAM . '=' . (int) $changes['page'];
		}

		return $path . ( $query ? '?' . implode( '&', $query ) : '' );
	}

	/**
	 * Whether a raw query key is one of $names, as `name`, `name[]`,
	 * `name[0]`, or with the brackets percent-encoded (as a GET form sends them).
	 *
	 * @param string   $raw_key Key as it appears in the query string.
	 * @param string[] $names   Param names.
	 * @return bool
	 */
	private static function is_param( $raw_key, array $names ) {
		$key = str_ireplace( array( '%5B', '%5D' ), array( '[', ']' ), $raw_key );
		foreach ( $names as $name ) {
			if ( $key === $name || 0 === strpos( $key, $name . '[' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Hidden inputs for the current query string minus $exclude, so a GET
	 * form (which replaces the whole query string) keeps other params, such
	 * as ?page_id= under plain permalinks.
	 *
	 * @param string[] $exclude Param names the form sets itself.
	 */
	public static function hidden_inputs( array $exclude ) {
		$query = array();
		wp_parse_str( (string) wp_parse_url( remove_query_arg( $exclude ), PHP_URL_QUERY ), $query );

		foreach ( $query as $name => $value ) {
			foreach ( (array) $value as $item ) {
				if ( is_scalar( $item ) ) {
					printf(
						'<input type="hidden" name="%s" value="%s" />',
						esc_attr( is_array( $value ) ? $name . '[]' : $name ),
						esc_attr( $item )
					);
				}
			}
		}
	}
}
