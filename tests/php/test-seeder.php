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
		remove_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		remove_filter( 'query', array( $this, 'fail_term_relationship_inserts' ) );
		$this->rmdir( $this->uploads ); // Deletes the files.
		$this->delete_folders( $this->uploads );

		parent::tear_down();
	}

	/**
	 * Points uploads at the temporary folder. The base moves too, so stored
	 * paths are relative to it, as on a real site.
	 *
	 * @param array $dirs Upload dir data.
	 * @return array
	 */
	public function temporary_upload_dir( $dirs ) {
		$dirs['path']    = $this->uploads;
		$dirs['basedir'] = $this->uploads;
		$dirs['subdir']  = '';
		$dirs['url']     = $dirs['baseurl'];
		return $dirs;
	}

	/**
	 * Makes the uploads folder unavailable, as on a full or read-only disk.
	 *
	 * The failure uses its own path, because wp_upload_dir() remembers each
	 * path's error for the rest of the request; otherwise the retry, which
	 * on a real site is a later request, would still see it.
	 *
	 * @param array $dirs Upload dir data.
	 * @return array
	 */
	public function broken_upload_dir( $dirs ) {
		$dirs['path']  = $this->uploads . '/unavailable';
		$dirs['error'] = 'Simulated unwritable uploads folder.';
		return $dirs;
	}

	/**
	 * Makes every post-term assignment fail at the database, which is how
	 * wp_set_object_terms() reports an error.
	 *
	 * @param string $query SQL.
	 * @return string
	 */
	public function fail_term_relationship_inserts( $query ) {
		global $wpdb;
		if ( 0 === stripos( ltrim( $query ), "INSERT INTO `{$wpdb->term_relationships}`" ) ) {
			return "SELECT * FROM `{$wpdb->prefix}wmpgf_no_such_table`";
		}
		return $query;
	}

	/**
	 * Runs the seeder once.
	 */
	private function seed() {
		( new WMPGF_Seeder() )->seed();
	}

	/**
	 * Whether the seeder marked its work complete.
	 *
	 * @return bool
	 */
	private function is_complete() {
		return (bool) get_option( WMPGF_Seeder::SEEDED_OPTION );
	}

	/**
	 * IDs on one of the seeder's "created" lists, sorted.
	 *
	 * @param string $option Option name.
	 * @return int[]
	 */
	private function owned( $option ) {
		$ids = array_map( 'intval', (array) get_option( $option, array() ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Entries from demo-content.php.
	 *
	 * @return array[]
	 */
	private function demo_posts() {
		return require WMPGF_DIR . 'includes/demo-content.php';
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
					'post_status' => array( 'publish', 'draft', 'private', 'trash', 'inherit' ),
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * Term names of a post, sorted.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function term_names( $post_id, $taxonomy ) {
		$names = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		sort( $names );
		return $names;
	}

	/**
	 * Whether a post has a featured image whose file exists.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function has_cover_file( $post_id ) {
		$attachment_id = get_post_thumbnail_id( $post_id );
		return $attachment_id && file_exists( get_attached_file( $attachment_id ) );
	}

	/**
	 * Asserts every demo entry has a complete, owned post.
	 */
	private function assert_demo_posts_complete() {
		$owned = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		foreach ( $this->demo_posts() as $demo_post ) {
			$matches = get_posts(
				array(
					'post_type'   => WMPGF_Post_Type::POST_TYPE,
					'post__in'    => $owned,
					'meta_key'    => WMPGF_Seeder::DEMO_KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => sanitize_title( $demo_post['title'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_status' => 'any',
				)
			);
			$this->assertCount( 1, $matches, $demo_post['title'] );

			$post = $matches[0];
			$this->assertSame( 'publish', $post->post_status );
			$this->assertNotSame( '', $post->post_excerpt );
			$this->assertTrue( $this->has_cover_file( $post->ID ), $demo_post['title'] . ' has a cover' );

			$categories = $demo_post['categories'];
			$tags       = $demo_post['tags'];
			sort( $categories );
			sort( $tags );
			$this->assertSame( $categories, $this->term_names( $post->ID, WMPGF_Post_Type::TAX_CATEGORY ) );
			$this->assertSame( $tags, $this->term_names( $post->ID, WMPGF_Post_Type::TAX_TAG ) );
		}
	}

	/**
	 * Asserts the recorded demo page is the seeder's, published, with the
	 * filter and a grid containing pagination.
	 *
	 * @return WP_Post The page.
	 */
	private function assert_demo_page_complete() {
		$this->assertTrue( (bool) get_option( WMPGF_Seeder::DEMO_PAGE_OWNED_OPTION ) );
		$page = get_post( (int) get_option( WMPGF_Seeder::DEMO_PAGE_OPTION ) );
		$this->assertSame( 'publish', $page->post_status );

		$blocks = parse_blocks( $page->post_content );
		$names  = wp_list_pluck( $blocks, 'blockName' );
		$this->assertContains( 'wmpgf/posts-filter', $names );
		$grid = $blocks[ array_search( 'wmpgf/posts-grid', $names, true ) ];
		$this->assertSame( 'wmpgf/pagination', $grid['innerBlocks'][0]['blockName'] );

		return $page;
	}

	/**
	 * Runs the real uninstall.php. It defines no functions, so it can be
	 * included more than once.
	 */
	private function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wm-posts-grid-filter/wm-posts-grid-filter.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core's constant, which uninstall.php checks for.
		}
		require WMPGF_DIR . 'uninstall.php';
	}

	public function test_fresh_activation_creates_complete_demo_content() {
		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assert_demo_posts_complete();
		$page = $this->assert_demo_page_complete();
		$this->assertSame( WMPGF_Seeder::DEMO_PAGE_SLUG, $page->post_name );

		$demo_count = count( $this->demo_posts() );
		$this->assertCount( $demo_count, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$this->assertCount( $demo_count, $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION ) );
		$this->assertCount( 10, $this->owned( WMPGF_Seeder::SEEDED_TERM_IDS_OPTION ) );
	}

	public function test_seeding_twice_creates_no_duplicates() {
		$this->seed();
		$posts = $this->count_posts( WMPGF_Post_Type::POST_TYPE );
		$pages = $this->count_posts( 'page' );

		delete_option( WMPGF_Seeder::SEEDED_OPTION );
		$this->seed();

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
		$this->seed();
		remove_filter( 'wp_insert_post_empty_content', $block, 10 );

		$this->assertFalse( $this->is_complete() );

		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assert_demo_posts_complete();
	}

	public function test_failed_image_creation_is_not_complete_and_a_retry_adds_the_covers() {
		add_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		$this->seed();
		remove_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );

		$this->assertFalse( $this->is_complete() );
		$post_ids = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$this->assertCount( count( $this->demo_posts() ), $post_ids );
		$this->assertSame( array(), $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION ) );
		foreach ( $post_ids as $post_id ) {
			$this->assertFalse( has_post_thumbnail( $post_id ) );
		}

		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ), 'The same posts were repaired, not recreated.' );
		$this->assertSame( count( $post_ids ), $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertCount( count( $post_ids ), $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION ) );
		$this->assert_demo_posts_complete();
	}

	public function test_failed_term_assignment_is_not_complete_and_a_retry_assigns_the_terms() {
		global $wpdb;

		add_filter( 'query', array( $this, 'fail_term_relationship_inserts' ) );
		$suppress = $wpdb->suppress_errors( true );
		$this->seed();
		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', array( $this, 'fail_term_relationship_inserts' ) );

		$this->assertFalse( $this->is_complete() );
		$post_ids = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$this->assertSame( array(), $this->term_names( $post_ids[0], WMPGF_Post_Type::TAX_CATEGORY ) );

		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$this->assertSame( count( $post_ids ), $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assert_demo_posts_complete();
	}

	public function test_a_retry_repairs_incomplete_owned_posts_and_page_without_duplicates() {
		$this->seed();
		list( $drafted, $untagged, $lost_file, $trashed ) = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$page_id = (int) get_option( WMPGF_Seeder::DEMO_PAGE_OPTION );

		wp_update_post(
			array(
				'ID'           => $drafted,
				'post_status'  => 'draft',
				'post_excerpt' => '',
			)
		);
		delete_post_thumbnail( $untagged );
		wp_set_object_terms( $untagged, array(), WMPGF_Post_Type::TAX_TAG );
		wp_delete_file( get_attached_file( get_post_thumbnail_id( $lost_file ) ) );
		wp_trash_post( $trashed );
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:wmpgf/posts-filter /-->',
			)
		);
		$post_ids = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$pages    = $this->count_posts( 'page' );

		delete_option( WMPGF_Seeder::SEEDED_OPTION );
		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assert_demo_posts_complete();
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$this->assertSame( count( $post_ids ), $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertSame( $page_id, $this->assert_demo_page_complete()->ID );
		$this->assertSame( $pages, $this->count_posts( 'page' ) );
	}

	public function test_ownership_survives_a_partial_failure_so_uninstall_still_cleans_up() {
		add_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		$this->seed();
		remove_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		$this->assertFalse( $this->is_complete() );

		$this->uninstall();

		$this->assertSame( 0, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertSame( 0, $this->count_posts( 'page' ) );
		$this->assertNull( term_exists( 'Technology', WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertNull( term_exists( 'Trends', WMPGF_Post_Type::TAX_TAG ) );
	}

	public function test_posts_seeded_by_2_0_are_recognised_through_the_ownership_list() {
		$this->seed();
		$post_ids = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		foreach ( $post_ids as $post_id ) {
			delete_post_meta( $post_id, WMPGF_Seeder::DEMO_KEY_META );
		}

		delete_option( WMPGF_Seeder::SEEDED_OPTION );
		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$this->assert_demo_posts_complete();
	}

	public function test_unrelated_content_with_a_matching_title_or_slug_is_left_untouched() {
		$title     = $this->demo_posts()[0]['title'];
		$user_post = self::factory()->post->create(
			array(
				'post_type'    => WMPGF_Post_Type::POST_TYPE,
				'post_title'   => $title,
				'post_status'  => 'draft',
				'post_excerpt' => '',
				'post_content' => 'Written by the site owner.',
			)
		);
		$user_page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => WMPGF_Seeder::DEMO_PAGE_SLUG,
				'post_status'  => 'publish',
				'post_content' => 'The site owner\'s page.',
			)
		);

		$this->seed();

		$this->assertTrue( $this->is_complete() );
		$this->assert_demo_posts_complete();
		$this->assertNotContains( $user_post, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$this->assertSame( count( $this->demo_posts() ) + 1, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );

		$post = get_post( $user_post );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( '', $post->post_excerpt );
		$this->assertSame( 'Written by the site owner.', $post->post_content );
		$this->assertFalse( has_post_thumbnail( $user_post ) );
		$this->assertSame( array(), $this->term_names( $user_post, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertSame( '', get_post_meta( $user_post, WMPGF_Seeder::DEMO_KEY_META, true ) );

		$page = get_post( $user_page );
		$this->assertSame( WMPGF_Seeder::DEMO_PAGE_SLUG, $page->post_name );
		$this->assertSame( 'The site owner\'s page.', $page->post_content );
		$demo_page = $this->assert_demo_page_complete();
		$this->assertNotSame( $user_page, $demo_page->ID );
		$this->assertSame( WMPGF_Seeder::DEMO_PAGE_SLUG . '-2', $demo_page->post_name );

		$this->uninstall();

		$this->assertNotNull( get_post( $user_post ) );
		$this->assertNotNull( get_post( $user_page ) );
	}

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

		$this->seed();
		$this->assertGreaterThan( 1, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );

		$this->uninstall();

		$this->assertSame( 1, $this->count_posts( WMPGF_Post_Type::POST_TYPE ) );
		$this->assertNotNull( get_post( $hand_made ) );
		$this->assertSame( 0, $this->count_posts( 'attachment' ) );
		$this->assertNull( get_page_by_path( WMPGF_Seeder::DEMO_PAGE_SLUG ) );
		$this->assertInstanceOf( 'WP_Term', get_term( $existing_term, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertNull( term_exists( 'Technology', WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertFalse( get_option( WMPGF_Seeder::SEEDED_OPTION ) );
	}

	public function test_uninstall_keeps_seeded_terms_that_surviving_posts_use() {
		$this->seed();

		$draft   = self::factory()->post->create(
			array(
				'post_type'   => WMPGF_Post_Type::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$private = self::factory()->post->create(
			array(
				'post_type'   => WMPGF_Post_Type::POST_TYPE,
				'post_status' => 'private',
			)
		);
		wp_set_object_terms( $draft, 'Technology', WMPGF_Post_Type::TAX_CATEGORY );
		wp_set_object_terms( $private, 'News', WMPGF_Post_Type::TAX_TAG );

		$this->uninstall();

		$this->assertSame( array( 'Technology' ), $this->term_names( $draft, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertSame( array( 'News' ), $this->term_names( $private, WMPGF_Post_Type::TAX_TAG ) );
		$this->assertNull( term_exists( 'Culture', WMPGF_Post_Type::TAX_CATEGORY ), 'An unused seeded category is still removed.' );
		$this->assertNull( term_exists( 'Trends', WMPGF_Post_Type::TAX_TAG ), 'An unused seeded tag is still removed.' );
	}

	public function test_uninstall_keeps_seeded_images_that_surviving_content_uses() {
		$this->seed();
		list( $as_thumbnail, $in_content ) = $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION );

		$user_post = self::factory()->post->create( array( 'post_type' => WMPGF_Post_Type::POST_TYPE ) );
		set_post_thumbnail( $user_post, $as_thumbnail );
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => sprintf( '<img class="wp-image-%d" src="%s" alt="" />', $in_content, wp_get_attachment_url( $in_content ) ),
			)
		);

		$this->uninstall();

		$this->assertSame( 'attachment', get_post_type( $as_thumbnail ) );
		$this->assertTrue( file_exists( get_attached_file( $as_thumbnail ) ) );
		$this->assertSame( 'attachment', get_post_type( $in_content ) );
		$this->assertSame( 2, $this->count_posts( 'attachment' ), 'Every other seeded image is removed.' );
	}
}
