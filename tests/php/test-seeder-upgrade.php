<?php
/**
 * Upgrading an install seeded by 2.0.0, which could mark seeding complete
 * with featured images or terms missing.
 *
 * @package WMPGF
 */

/**
 * The one-time check of demo content seeded by an earlier version.
 */
class Test_WMPGF_Seeder_Upgrade extends WMPGF_TestCase {

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
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );
	}

	public function tear_down() {
		remove_filter( 'upload_dir', array( $this, 'temporary_upload_dir' ) );
		remove_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );
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
		$dirs['path']    = $this->uploads;
		$dirs['basedir'] = $this->uploads;
		$dirs['subdir']  = '';
		$dirs['url']     = $dirs['baseurl'];
		return $dirs;
	}

	/**
	 * Makes the uploads folder unavailable. Its own path, because
	 * wp_upload_dir() remembers each path's error for the request.
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
	 * The demo-content.php entry with a title.
	 *
	 * @param string $title Title.
	 * @return array
	 */
	private function entry( $title ) {
		foreach ( require WMPGF_DIR . 'includes/demo-content.php' as $demo_post ) {
			if ( $demo_post['title'] === $title ) {
				return $demo_post;
			}
		}
		return array();
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
	 * An install as 2.0.0 left it: seeded long ago, wmpgf_seeded true, the
	 * ownership records, and none of what later versions add (no validated
	 * version, no demo-key meta on the posts).
	 *
	 * @return int[] The seeded post IDs.
	 */
	private function legacy_install() {
		global $wpdb;

		( new WMPGF_Seeder() )->seed();
		delete_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION );

		$post_ids = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		foreach ( $post_ids as $post_id ) {
			delete_post_meta( $post_id, WMPGF_Seeder::DEMO_KEY_META );
			// Seeded long ago, so a later edit changes the modified date.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture.
				$wpdb->posts,
				array(
					'post_date'         => '2026-01-01 00:00:00',
					'post_date_gmt'     => '2026-01-01 00:00:00',
					'post_modified'     => '2026-01-01 00:00:00',
					'post_modified_gmt' => '2026-01-01 00:00:00',
				),
				array( 'ID' => $post_id )
			);
			clean_post_cache( $post_id );
		}
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );

		$this->assertTrue( (bool) get_option( WMPGF_Seeder::SEEDED_OPTION ), '2.0.0 marked it complete.' );
		$this->assertTrue( WMPGF_Seeder::needs_validation() );
		return $post_ids;
	}

	/**
	 * What happens after an in-place update: a request's init, as hooked,
	 * then WP-Cron running the event it scheduled.
	 */
	private function update_in_place() {
		$this->assertSame( 20, has_action( 'init', array( 'WMPGF_Seeder', 'schedule_validation' ) ) );
		WMPGF_Seeder::schedule_validation();
		$this->assertNotFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ), 'The check is scheduled.' );
		$this->assertNotFalse( has_action( WMPGF_Seeder::VALIDATION_HOOK ) );

		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK ); // WP-Cron unschedules an event as it runs it.
		do_action( WMPGF_Seeder::VALIDATION_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own hook, through a constant.
	}

	public function test_an_update_repairs_what_2_0_left_missing_and_nothing_else() {
		list( $no_cover, $no_terms, $edited, $deleted ) = $this->legacy_install();
		$post_ids                                       = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$attachments                                    = $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION );

		// What 2.0.0 could leave: a failed cover, failed term assignment.
		delete_post_thumbnail( $no_cover );
		wp_set_object_terms( $no_terms, array(), WMPGF_Post_Type::TAX_CATEGORY );
		wp_set_object_terms( $no_terms, array(), WMPGF_Post_Type::TAX_TAG );

		// The owner's own changes since: an edited post whose image they
		// removed, a deleted post, and an unrelated post with a demo title.
		wp_update_post(
			array(
				'ID'           => $edited,
				'post_content' => 'Rewritten by the owner.',
			)
		);
		delete_post_thumbnail( $edited );
		$edited_modified = get_post( $edited )->post_modified_gmt;
		wp_delete_post( $deleted, true );
		$unrelated  = self::factory()->post->create(
			array(
				'post_type'   => WMPGF_Post_Type::POST_TYPE,
				'post_title'  => get_post( $no_cover )->post_title,
				'post_status' => 'draft',
			)
		);
		$post_count = (int) wp_count_posts( WMPGF_Post_Type::POST_TYPE )->publish;

		$this->update_in_place();

		// Repaired, on the same posts.
		$this->assertTrue( $this->has_cover_file( $no_cover ) );
		$entry      = $this->entry( get_post( $no_terms )->post_title );
		$categories = $entry['categories'];
		$tags       = $entry['tags'];
		sort( $categories );
		sort( $tags );
		$this->assertSame( $categories, $this->term_names( $no_terms, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertSame( $tags, $this->term_names( $no_terms, WMPGF_Post_Type::TAX_TAG ) );

		// The owner's changes stand; nothing was recreated or added.
		$this->assertFalse( has_post_thumbnail( $edited ) );
		$this->assertSame( 'Rewritten by the owner.', get_post( $edited )->post_content );
		$this->assertSame( $edited_modified, get_post( $edited )->post_modified_gmt );
		$this->assertNull( get_post( $deleted ) );
		$this->assertSame( $post_count, (int) wp_count_posts( WMPGF_Post_Type::POST_TYPE )->publish );
		$this->assertSame( 'draft', get_post_status( $unrelated ) );
		$this->assertFalse( has_post_thumbnail( $unrelated ) );
		$this->assertSame( '', get_post_meta( $unrelated, WMPGF_Seeder::DEMO_KEY_META, true ) );

		// Ownership records kept, and the new cover added to them.
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$after = $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION );
		$this->assertSame( array(), array_diff( $attachments, $after ), 'No attachment record lost.' );
		$this->assertContains( get_post_thumbnail_id( $no_cover ), $after );

		// Recorded, once.
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$this->assertTrue( (bool) get_option( WMPGF_Seeder::SEEDED_OPTION ) );
		$this->assertFalse( WMPGF_Seeder::needs_validation() );
		WMPGF_Seeder::schedule_validation();
		$this->assertFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ), 'Nothing is scheduled again.' );
		$attachment_count = count(
			get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
		do_action( WMPGF_Seeder::VALIDATION_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own hook, through a constant.
		$this->assertSame(
			$attachment_count,
			count(
				get_posts(
					array(
						'post_type'   => 'attachment',
						'post_status' => 'inherit',
						'numberposts' => -1,
						'fields'      => 'ids',
					)
				)
			),
			'A second run changes nothing.'
		);
	}

	public function test_a_failed_repair_stays_retryable() {
		list( $no_cover ) = $this->legacy_install();
		delete_post_thumbnail( $no_cover );

		add_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		$this->update_in_place();
		remove_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );

		$this->assertFalse( has_post_thumbnail( $no_cover ) );
		$this->assertFalse( get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ), 'Not recorded after a failure.' );
		$this->assertTrue( (bool) get_option( WMPGF_Seeder::SEEDED_OPTION ) );
		$retry = wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK );
		$this->assertGreaterThanOrEqual( time() + WMPGF_Seeder::RETRY_DELAY - 60, $retry, 'Retried an hour later, not on every request.' );

		// The retry, once the uploads folder works again.
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );
		do_action( WMPGF_Seeder::VALIDATION_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own hook, through a constant.

		$this->assertTrue( $this->has_cover_file( $no_cover ) );
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$this->assertFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ) );
	}

	public function test_a_run_in_progress_is_not_repeated_but_a_dead_one_is_taken_over() {
		list( $no_cover ) = $this->legacy_install();
		delete_post_thumbnail( $no_cover );

		// Another request is repairing right now.
		add_option( WMPGF_Seeder::LOCK_OPTION, time(), '', false );
		$this->update_in_place();
		$this->assertFalse( has_post_thumbnail( $no_cover ) );
		$this->assertFalse( get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );

		// That request died long ago.
		update_option( WMPGF_Seeder::LOCK_OPTION, time() - WMPGF_Seeder::LOCK_TIMEOUT - 60, false );
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );
		do_action( WMPGF_Seeder::VALIDATION_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own hook, through a constant.

		$this->assertTrue( $this->has_cover_file( $no_cover ) );
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$this->assertFalse( get_option( WMPGF_Seeder::LOCK_OPTION ), 'The lock is released.' );
	}

	public function test_reactivation_runs_the_same_check() {
		list( , $no_terms ) = $this->legacy_install();
		wp_set_object_terms( $no_terms, array(), WMPGF_Post_Type::TAX_TAG );

		WMPGF_Plugin::activate();

		$this->assertNotSame( array(), $this->term_names( $no_terms, WMPGF_Post_Type::TAX_TAG ) );
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
	}

	public function test_a_fresh_install_records_the_version_and_needs_no_check() {
		WMPGF_Plugin::activate();

		$this->assertTrue( (bool) get_option( WMPGF_Seeder::SEEDED_OPTION ) );
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		WMPGF_Seeder::schedule_validation();
		$this->assertFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ) );
	}

	public function test_uninstall_removes_the_version_and_the_schedule() {
		$this->legacy_install();
		WMPGF_Seeder::schedule_validation();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wm-posts-grid-filter/wm-posts-grid-filter.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core's constant, which uninstall.php checks for.
		}
		update_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION, 1 );
		require WMPGF_DIR . 'uninstall.php';

		$this->assertFalse( get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$this->assertFalse( get_option( WMPGF_Seeder::LOCK_OPTION ) );
		$this->assertFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ) );
	}
}
