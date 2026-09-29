<?php
/**
 * Base test case.
 *
 * @package WMPGF
 */

/**
 * Clears the request state the plugin reads between tests.
 */
abstract class WMPGF_TestCase extends WP_UnitTestCase {

	/**
	 * WMPGF_Request and WMPGF_Query cache term slugs and query results for
	 * the request. A test run is one long request, so each test starts with
	 * empty caches and an empty query string.
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_request();
	}

	/**
	 * Empties the query string and the plugin's request caches.
	 */
	protected function reset_request() {
		$_GET                   = array();
		$_SERVER['REQUEST_URI'] = '/';

		$this->reset_static( 'WMPGF_Request', 'known_slugs' );
		$this->reset_static( 'WMPGF_Query', 'counts' );
		$this->reset_static( 'WMPGF_Query', 'pages' );
		$this->reset_static( 'WMPGF_Blocks', 'color_scheme_script_printed', false );
	}

	/**
	 * Resets a private static cache or flag.
	 *
	 * @param string $class_name Class name.
	 * @param string $property   Property name.
	 * @param mixed  $value      Initial value.
	 */
	private function reset_static( $class_name, $property, $value = array() ) {
		$reflection = new ReflectionProperty( $class_name, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}
}
