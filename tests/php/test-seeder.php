<?php
/**
 * Seeding on activation, and uninstall removing only what was seeded.
 *
 * @package WMPGF
 */

/**
 * Demo content lifecycle.
 */
class Test_WMPGF_Seeder extends WMPGF_TestCase {

	/**
	 * Temporary uploads folder, so cover images don't land in a real site.
	 *
	 * @var string
	 */
	private $uploads;

	public function set_up() {
		parent::set_up();

		$this->uploads = trailingslashit( get_temp_dir() ) . 'wmpgf-tests-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->uploads );
		add_filter( 'upload_dir', array( $this, 'temporary_upload_dir' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		remove_filter( 'upload_dir', array( $this, 'temporary_upload_dir' ) );
		$this->rmdir( $this->uploads ); // Deletes the files.
		$this->delete_folders( $this->uploads );

		parent::tear_down();
	}

	/**
	 * Points uploads at the temporary folder.
	 *
	 * @param array $dirs Upload dir data.
	 * @return array
	 */
	public function temporary_upload_dir( $dirs ) {
		$dirs['path']   = $this->uploads;
		$dirs['subdir'] = '';
		return $dirs;
	}

	/**
	 * Number of posts of a type, in any status.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function count_posts( $post_type ) {
		return count(
			get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	public function test_seeds_every_demo_post_with_terms_and_a_cover() {
		( new WMPGF_Seeder() )->seed();

		$demo_posts = require WMPGF_DIR . 'includes/demo-content.php';
		$this->assertSame( count( $demo_posts ), $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertTrue( (bool) get_option( WMPGF_Seeder::SEEDED_OPTION ) );

		$post = get_posts( array( 'post_type' => WMPGF_Post_Type::POST_TYPE ) )[0];
		$this->assertTrue( has_post_thumbnail( $post ) );
		$this->assertNotEmpty( wp_get_object_terms( $post->ID, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertNotEmpty( wp_get_object_terms( $post->ID, WMPGF_Post_Type::TAX_TAG ) );

		$page = get_post( (int) get_option( WMPGF_Seeder::DEMO_PAGE_OPTION ) );
		$this->assertSame( 'posts-grid-filter-demo', $page->post_name );
		$this->assertTrue( has_block( 'wmpgf/posts-grid', $page ) );
	}

	public function test_seeding_twice_creates_no_duplicates() {
		( new WMPGF_Seeder() )->seed();
		$posts = $this->count_posts( WMPGF_Post_Type::POST_TYPE );
		$pages = $this->count_posts( 'page' );

		// Even with the "done" flag cleared, every step reuses what exists.
		delete_option( WMPGF_Seeder::SEEDED_OPTION );
		( new WMPGF_Seeder() )->seed();

		$this->assertSame( $posts, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertSame( $pages, $this->count_posts( 'page' ) );
		$this->assertCount(
			4,
			get_terms(
				array(
					'taxonomy'   => WMPGF_Post_Type::TAX_CATEGORY,
					'hide_empty' => false,
				)
			)
		);
	}

	public function test_a_failed_post_leaves_seeding_open_for_a_retry() {
		$block = static function ( $maybe_empty, $data ) {
			return WMPGF_Post_Type::POST_TYPE === $data['post_type'] ? true : $maybe_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $block, 10, 2 );
		( new WMPGF_Seeder() )->seed();
		remove_filter( 'wp_insert_post_empty_content', $block, 10 );

		$this->assertFalse( get_option( WMPGF_Seeder::SEEDED_OPTION ) );

		( new WMPGF_Seeder() )->seed();

		$this->assertTrue( (bool) get_option( WMPGF_Seeder::SEEDED_OPTION ) );
	}

	/**
	 * Runs the real uninstall.php. It defines no functions, so including it
	 * inside a test is safe.
	 */
	public function test_uninstall_removes_only_seeded_content() {
		$hand_made = self::factory()->post->create(
			array(
				'post_type'  => WMPGF_Post_Type::POST_TYPE,
				'post_title' => 'Written by the site owner',
			)
		);
		// A term that already exists is reused by the seeder, not owned.
		$existing_term = self::factory()->term->create(
			array(
				'taxonomy' => WMPGF_Post_Type::TAX_CATEGORY,
				'name'     => 'Design',
			)
		);

		( new WMPGF_Seeder() )->seed();
		$this->assertGreaterThan( 1, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wm-posts-grid-filter/wm-posts-grid-filter.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core's constant, which uninstall.php checks for.
		}
		require WMPGF_DIR . 'uninstall.php';

		$this->assertSame( 1, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertNotNull( get_post( $hand_made ) );
		$this->assertSame( 0, $this->count_posts( 'attachment' ) );
		$this->assertNull( get_page_by_path( 'posts-grid-filter-demo' ) );
		$this->assertInstanceOf( 'WP_Term', get_term( $existing_term, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertNull( term_exists( 'Technology', WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertFalse( get_option( WMPGF_Seeder::SEEDED_OPTION ) );
	}
}
