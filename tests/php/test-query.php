<?php
/**
 * Filter logic: URL params in, matching posts out.
 *
 * @package WMPGF
 */

/**
 * Covers WMPGF_Request (reading the URL) and WMPGF_Query (querying).
 */
class Test_WMPGF_Query extends WMPGF_TestCase {

	/**
	 * Post IDs by name.
	 *
	 * @var int[]
	 */
	private static $posts = array();

	/**
	 * Five posts.
	 *
	 * Name, title, categories, tags:
	 *
	 *   alpha  "Alpha tokens"  design           news
	 *   beta   "Beta tokens"   culture          news
	 *   gamma  "Gamma"         design, culture  guide
	 *   delta  "Delta"         tech             guide
	 *   eps    "Epsilon"       tech             news
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		foreach ( array( 'design', 'culture', 'tech' ) as $slug ) {
			$factory->term->create(
				array(
					'taxonomy' => WMPGF_Post_Type::TAX_CATEGORY,
					'name'     => ucfirst( $slug ),
					'slug'     => $slug,
				)
			);
		}
		foreach ( array( 'news', 'guide' ) as $slug ) {
			$factory->term->create(
				array(
					'taxonomy' => WMPGF_Post_Type::TAX_TAG,
					'name'     => ucfirst( $slug ),
					'slug'     => $slug,
				)
			);
		}

		$fixtures = array(
			'alpha' => array( 'Alpha tokens', array( 'design' ), array( 'news' ) ),
			'beta'  => array( 'Beta tokens', array( 'culture' ), array( 'news' ) ),
			'gamma' => array( 'Gamma', array( 'design', 'culture' ), array( 'guide' ) ),
			'delta' => array( 'Delta', array( 'tech' ), array( 'guide' ) ),
			'eps'   => array( 'Epsilon', array( 'tech' ), array( 'news' ) ),
		);
		foreach ( $fixtures as $name => list( $title, $categories, $tags ) ) {
			$id = $factory->post->create(
				array(
					'post_type'  => WMPGF_Post_Type::POST_TYPE,
					'post_title' => $title,
				)
			);
			wp_set_object_terms( $id, $categories, WMPGF_Post_Type::TAX_CATEGORY );
			wp_set_object_terms( $id, $tags, WMPGF_Post_Type::TAX_TAG );
			self::$posts[ $name ] = $id;
		}
	}

	/**
	 * Names of the posts matching the current $_GET, in any order.
	 *
	 * @return string[]
	 */
	private function matching() {
		$result = WMPGF_Query::page( WMPGF_Request::filters(), 24, 1 );
		$names  = array();
		foreach ( $result['query']->posts as $post ) {
			$names[] = array_search( $post->ID, self::$posts, true );
		}
		sort( $names );
		return $names;
	}

	public function test_no_filters_returns_every_post() {
		$this->assertSame( array( 'alpha', 'beta', 'delta', 'eps', 'gamma' ), $this->matching() );
	}

	public function test_two_categories_return_the_union() {
		$_GET['wmpgf-category'] = 'design,culture';

		$this->assertSame( array( 'alpha', 'beta', 'gamma' ), $this->matching() );
	}

	public function test_category_and_tag_return_the_intersection() {
		$_GET['wmpgf-category'] = 'design';
		$_GET['wmpgf-tag']      = 'news';

		$this->assertSame( array( 'alpha' ), $this->matching() );
	}

	public function test_search_narrows_categories_and_tags_further() {
		$_GET['wmpgf-category'] = 'design,culture';
		$_GET['wmpgf-tag']      = 'news';
		$this->assertSame( array( 'alpha', 'beta' ), $this->matching() );

		$_GET['wmpgf-search'] = 'alpha';
		$this->assertSame( array( 'alpha' ), $this->matching() );
	}

	public function test_count_matches_the_results() {
		$_GET['wmpgf-tag'] = 'news';

		$this->assertSame( 3, WMPGF_Query::count( WMPGF_Request::filters() ) );
	}

	public function test_unknown_slugs_are_ignored() {
		$_GET['wmpgf-category'] = 'design,no-such-term';
		$this->assertSame( array( 'design' ), WMPGF_Request::filters()['categories'] );

		// Only unknown slugs means no category filter at all.
		$_GET['wmpgf-category'] = 'no-such-term';
		$this->assertSame( array(), WMPGF_Request::filters()['categories'] );
	}

	public function test_array_and_comma_forms_parse_the_same() {
		$_GET['wmpgf-category'] = 'design,culture';
		$comma                  = WMPGF_Request::filters();

		$_GET['wmpgf-category'] = array( 'design', 'culture' );
		$array                  = WMPGF_Request::filters();

		$this->assertSame( array( 'design', 'culture' ), $comma['categories'] );
		$this->assertSame( $comma, $array );
	}

	public function test_slugs_are_sanitized_and_deduplicated() {
		$_GET['wmpgf-category'] = 'Design,design,<b>culture</b>';

		$this->assertSame( array( 'design', 'culture' ), WMPGF_Request::filters()['categories'] );
	}

	public function test_search_is_plain_text_and_capped() {
		$_GET['wmpgf-search'] = '<script>x</script> ' . str_repeat( 'a', 200 );

		$search = WMPGF_Request::filters()['search'];

		$this->assertStringNotContainsString( '<', $search );
		$this->assertSame( WMPGF_Request::MAX_SEARCH_LENGTH, mb_strlen( $search ) );
	}

	/**
	 * A visitor's page size wins when valid; anything else falls back to the block's.
	 *
	 * @dataProvider data_per_page
	 *
	 * @param mixed $requested     ?wmpgf-per-page= value, or null for none.
	 * @param mixed $block_default The block's attribute.
	 * @param int   $expected      Page size used.
	 */
	public function test_per_page_is_clamped( $requested, $block_default, $expected ) {
		if ( null !== $requested ) {
			$_GET['wmpgf-per-page'] = $requested;
		}

		$this->assertSame( $expected, WMPGF_Request::per_page( $block_default ) );
	}

	/**
	 * Requested value, block default, expected page size.
	 *
	 * @return array[]
	 */
	public function data_per_page() {
		return array(
			'no param uses the block default' => array( null, 6, 6 ),
			'valid request wins'              => array( '12', 6, 12 ),
			'above the maximum'               => array( '500', 6, 24 ),
			'zero falls back'                 => array( '0', 6, 6 ),
			'negative falls back'             => array( '-5', 6, 6 ),
			'not a number falls back'         => array( 'abc', 6, 6 ),
			'array falls back'                => array( array( '12' ), 6, 6 ),
			'block default above maximum'     => array( null, 100, 24 ),
			'block default of -1 (all posts)' => array( null, -1, 1 ),
		);
	}

	public function test_out_of_range_page_clamps_to_the_last_page() {
		$result = WMPGF_Query::page( WMPGF_Request::filters(), 2, 9 );

		$this->assertSame( 3, $result['total_pages'] );
		$this->assertSame( 3, $result['page'] );
		$this->assertCount( 1, $result['query']->posts );
	}

	/**
	 * Anything but a positive whole number is page 1.
	 *
	 * @dataProvider data_page
	 *
	 * @param mixed $requested ?wmpgf-page= value.
	 * @param int   $expected  Page read.
	 */
	public function test_page_param( $requested, $expected ) {
		$_GET['wmpgf-page'] = $requested;

		$this->assertSame( $expected, WMPGF_Request::page() );
	}

	/**
	 * Requested value, expected page.
	 *
	 * @return array[]
	 */
	public function data_page() {
		return array(
			'valid'    => array( '2', 2 ),
			'zero'     => array( '0', 1 ),
			'negative' => array( '-3', 1 ),
			'text'     => array( 'abc', 1 ),
			'array'    => array( array( '2' ), 1 ),
		);
	}
}
