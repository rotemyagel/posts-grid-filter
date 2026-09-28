<?php
/**
 * Regression test for the 1.3 bug where the post type's permalink
 * (/%wmpgf_category%/%postname%/) had no fixed prefix, so its rewrite rule
 * matched every two-segment URL and 404'd core archives.
 *
 * @package WMPGF
 */

/**
 * Core URLs resolve to core queries with the plugin active.
 */
class Test_WMPGF_Rewrite extends WMPGF_TestCase {

	/**
	 * WordPress adds a post type's permastruct only when it's registered
	 * under pretty permalinks. The test site boots with plain ones, so the
	 * plugin's types are registered again here; without that, these tests
	 * would pass without the plugin's rules in the table at all.
	 */
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );

		$post_type = new WMPGF_Post_Type();
		$post_type->register_taxonomies();
		$post_type->register_post_type();
		flush_rewrite_rules( false );
	}

	public function test_plugin_rules_are_in_the_table() {
		$ours = preg_grep( '#^grid-post/#', array_keys( get_option( 'rewrite_rules' ) ) );

		$this->assertNotEmpty( $ours );
	}

	public function test_author_archive() {
		$author = self::factory()->user->create(
			array(
				'user_login' => 'rewrite-author',
				'role'       => 'author',
			)
		);
		self::factory()->post->create( array( 'post_author' => $author ) );

		$this->go_to( '/author/rewrite-author/' );

		$this->assertQueryTrue( 'is_author', 'is_archive' );
	}

	public function test_month_archive() {
		self::factory()->post->create( array( 'post_date' => '2026-09-15 10:00:00' ) );

		$this->go_to( '/2026/09/' );

		$this->assertQueryTrue( 'is_date', 'is_month', 'is_archive' );
	}

	public function test_atom_feed() {
		self::factory()->post->create();

		$this->go_to( '/feed/atom/' );

		$this->assertQueryTrue( 'is_feed' );
	}

	public function test_second_page_of_posts() {
		update_option( 'posts_per_page', 1 );
		self::factory()->post->create_many( 2 );

		$this->go_to( '/page/2/' );

		$this->assertQueryTrue( 'is_home', 'is_front_page', 'is_paged' );
	}

	public function test_nested_page() {
		$parent = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'parent',
			)
		);
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'child',
				'post_parent' => $parent,
			)
		);

		$this->go_to( '/parent/child/' );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
		$this->assertSame( $child, get_queried_object_id() );
	}

	public function test_plugin_post_resolves_under_its_own_prefix() {
		$post = self::factory()->post->create(
			array(
				'post_type' => WMPGF_Post_Type::POST_TYPE,
				'post_name' => 'grid-demo',
			)
		);

		$this->assertStringEndsWith( '/grid-post/grid-demo/', get_permalink( $post ) );

		$this->go_to( get_permalink( $post ) );

		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertSame( $post, get_queried_object_id() );
	}
}
