<?php
/**
 * WMPGF_Request::url(), the PHP twin of urlWith() in src/shared/url.js.
 *
 * @package WMPGF
 */

/**
 * Builds pagination and filter links from the current request URI.
 */
class Test_WMPGF_Url extends WMPGF_TestCase {

	public function test_keeps_unrelated_params_exactly() {
		$_SERVER['REQUEST_URI'] = '/demo/?utm_source=a%2Cb&wmpgf-category=design,culture';

		$this->assertSame(
			'/demo/?utm_source=a%2Cb&wmpgf-category=design,culture&wmpgf-page=2',
			WMPGF_Request::url( array( 'page' => 2 ) )
		);
	}

	public function test_replaces_its_own_params_in_every_form() {
		$_SERVER['REQUEST_URI'] = '/demo/?wmpgf-category[]=a&wmpgf-category%5B%5D=b&wmpgf-category%5b0%5d=c&wmpgf-category=d&keep=1';

		$this->assertSame(
			'/demo/?keep=1&wmpgf-category=design',
			WMPGF_Request::url( array( 'categories' => array( 'design' ) ) )
		);
	}

	public function test_omits_page_one_and_resets_the_page() {
		$_SERVER['REQUEST_URI'] = '/demo/?wmpgf-page=3';

		$this->assertSame( '/demo/', WMPGF_Request::url( array( 'page' => 1 ) ) );
		$this->assertSame( '/demo/?wmpgf-tag=news', WMPGF_Request::url( array( 'tags' => array( 'news' ) ) ) );
	}

	public function test_encodes_the_search_term() {
		$this->assertSame(
			'/?wmpgf-search=a%26b%20c%3Dd',
			WMPGF_Request::url( array( 'search' => 'a&b c=d' ) )
		);
	}

	public function test_path_can_not_become_a_protocol_relative_link() {
		$_SERVER['REQUEST_URI'] = '//evil.example/path';

		$this->assertSame( '/evil.example/path?wmpgf-page=2', WMPGF_Request::url( array( 'page' => 2 ) ) );
	}
}
