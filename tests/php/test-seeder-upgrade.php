<?php
/**
 * Upgrading an install seeded by 2.0.0, which could mark seeding complete
 * with featured images or terms missing, and the repair run on request.
 *
 * @package WMPGF
 */

/**
 * The one-time check of demo content seeded by an earlier version, which
 * changes nothing it can't attribute, and the explicit repair.
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

	/**
	 * Every attachment ID on the site, sorted.
	 *
	 * @return int[]
	 */
	private function attachment_ids() {
		$ids = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		sort( $ids );
		return $ids;
	}

	/**
	 * Removes a demo post's featured image and all its terms the way a site
	 * owner, WP-CLI, the REST API or another plugin can: directly, without
	 * saving the post. Its dates don't change, so nothing stored tells this
	 * apart from what a failed 2.0.0 seed left.
	 *
	 * @param int $post_id Post ID.
	 */
	private function remove_directly( $post_id ) {
		$before = get_post( $post_id )->post_modified_gmt;

		delete_post_thumbnail( $post_id );
		foreach ( array( WMPGF_Post_Type::TAX_CATEGORY, WMPGF_Post_Type::TAX_TAG ) as $taxonomy ) {
			wp_remove_object_terms( $post_id, wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) ), $taxonomy );
		}

		clean_post_cache( $post_id );
		$post = get_post( $post_id );
		$this->assertSame( $before, $post->post_modified_gmt, 'A direct edit leaves the modified date alone.' );
		$this->assertSame( $post->post_date_gmt, $post->post_modified_gmt, 'So the post looks untouched since seeding.' );
	}

	public function test_an_update_checks_once_and_changes_nothing_it_cannot_attribute() {
		list( $no_cover, $no_terms, $edited, $deleted ) = $this->legacy_install();

		// Removed directly: a failed 2.0.0 seed, or the owner? Can't tell.
		delete_post_thumbnail( $no_cover );
		$this->remove_directly( $no_terms );

		// The owner's own changes: an edited post whose image they removed,
		// a deleted post, and an unrelated post with a demo title.
		wp_update_post(
			array(
				'ID'           => $edited,
				'post_content' => 'Rewritten by the owner.',
			)
		);
		delete_post_thumbnail( $edited );
		wp_delete_post( $deleted, true );
		$unrelated = self::factory()->post->create(
			array(
				'post_type'   => WMPGF_Post_Type::POST_TYPE,
				'post_title'  => get_post( $no_cover )->post_title,
				'post_status' => 'draft',
			)
		);

		$post_ids    = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$attachments = $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION );
		$terms       = $this->owned( WMPGF_Seeder::SEEDED_TERM_IDS_OPTION );
		$all_images  = $this->attachment_ids();

		$this->update_in_place();

		// Nothing restored: every removal stands.
		$this->assertFalse( has_post_thumbnail( $no_cover ) );
		$this->assertFalse( has_post_thumbnail( $no_terms ) );
		$this->assertSame( array(), $this->term_names( $no_terms, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertSame( array(), $this->term_names( $no_terms, WMPGF_Post_Type::TAX_TAG ) );
		$this->assertFalse( has_post_thumbnail( $edited ) );
		$this->assertSame( 'Rewritten by the owner.', get_post( $edited )->post_content );
		$this->assertNull( get_post( $deleted ) );
		$this->assertSame( 'draft', get_post_status( $unrelated ) );
		$this->assertSame( '', get_post_meta( $unrelated, WMPGF_Seeder::DEMO_KEY_META, true ) );
		$this->assertSame( $all_images, $this->attachment_ids(), 'No image was created.' );

		// Ownership records unchanged; 2.0.0 posts get their demo key.
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$this->assertSame( $attachments, $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION ) );
		$this->assertSame( $terms, $this->owned( WMPGF_Seeder::SEEDED_TERM_IDS_OPTION ) );
		$this->assertNotSame( '', get_post_meta( $no_cover, WMPGF_Seeder::DEMO_KEY_META, true ) );

		// Checked, once.
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$this->assertFalse( WMPGF_Seeder::needs_validation() );
		WMPGF_Seeder::schedule_validation();
		$this->assertFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ), 'Nothing is scheduled again.' );
	}

	public function test_the_requested_repair_restores_only_published_demo_posts() {
		list( $no_cover, $no_terms, $drafted, $deleted ) = $this->legacy_install();
		delete_post_thumbnail( $no_cover );
		$this->remove_directly( $no_terms );
		wp_update_post(
			array(
				'ID'          => $drafted,
				'post_status' => 'draft',
			)
		);
		delete_post_thumbnail( $drafted );
		wp_delete_post( $deleted, true );
		$deleted_tag = get_term_by( 'name', 'Trends', WMPGF_Post_Type::TAX_TAG );
		wp_delete_term( $deleted_tag->term_id, WMPGF_Post_Type::TAX_TAG );
		$unrelated = self::factory()->post->create(
			array(
				'post_type'  => WMPGF_Post_Type::POST_TYPE,
				'post_title' => get_post( $no_cover )->post_title,
			)
		);
		$this->update_in_place();
		$post_ids    = $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION );
		$attachments = $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION );
		$all_images  = $this->attachment_ids();

		// A dry run lists what would change, and changes nothing.
		$plan = ( new WMPGF_Seeder() )->repair_demo_content( true );
		$this->assertSame( 'planned', $plan['status'] );
		$this->assertCount( 2, $plan['changes'] );
		$this->assertStringContainsString( sprintf( '(ID %d) is missing a featured image', $no_cover ), $plan['changes'][0] . $plan['changes'][1] );
		$this->assertCount( 1, $plan['skipped'] );
		$this->assertStringContainsString( sprintf( '(ID %d)', $drafted ), $plan['skipped'][0] );
		$this->assertStringContainsString( 'it is draft', $plan['skipped'][0] );
		$this->assertFalse( has_post_thumbnail( $no_cover ) );
		$this->assertSame( $all_images, $this->attachment_ids() );

		$result = ( new WMPGF_Seeder() )->repair_demo_content();
		$this->assertSame( 'repaired', $result['status'] );
		$this->assertSame( array(), $result['problems'] );

		// Restored, on the same posts.
		$this->assertTrue( $this->has_cover_file( $no_cover ) );
		$this->assertTrue( $this->has_cover_file( $no_terms ) );
		$entry      = $this->entry( get_post( $no_terms )->post_title );
		$categories = $entry['categories'];
		$tags       = array_values( array_diff( $entry['tags'], array( 'Trends' ) ) );
		sort( $categories );
		sort( $tags );
		$this->assertSame( $categories, $this->term_names( $no_terms, WMPGF_Post_Type::TAX_CATEGORY ) );
		$this->assertSame( $tags, $this->term_names( $no_terms, WMPGF_Post_Type::TAX_TAG ) );

		// Left alone: the draft, the deleted post and term, the unrelated post.
		$this->assertSame( 'draft', get_post_status( $drafted ) );
		$this->assertFalse( has_post_thumbnail( $drafted ) );
		$this->assertNull( get_post( $deleted ) );
		$this->assertEmpty( term_exists( 'Trends', WMPGF_Post_Type::TAX_TAG ) );
		$this->assertFalse( has_post_thumbnail( $unrelated ) );
		$this->assertSame( '', get_post_meta( $unrelated, WMPGF_Seeder::DEMO_KEY_META, true ) );

		// Ownership records kept, the new covers added to them.
		$this->assertSame( $post_ids, $this->owned( WMPGF_Seeder::SEEDED_POST_IDS_OPTION ) );
		$after = $this->owned( WMPGF_Seeder::SEEDED_ATTACHMENT_IDS_OPTION );
		$this->assertSame( array(), array_diff( $attachments, $after ), 'No attachment record lost.' );
		$this->assertContains( get_post_thumbnail_id( $no_cover ), $after );
		$this->assertContains( get_post_thumbnail_id( $no_terms ), $after );

		// A second run has nothing to do.
		$images = $this->attachment_ids();
		$again  = ( new WMPGF_Seeder() )->repair_demo_content();
		$this->assertSame( 'nothing', $again['status'] );
		$this->assertSame( $images, $this->attachment_ids() );
	}

	public function test_a_failed_repair_can_be_run_again() {
		list( $no_cover ) = $this->legacy_install();
		delete_post_thumbnail( $no_cover );
		$this->update_in_place();

		add_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );
		$failed = ( new WMPGF_Seeder() )->repair_demo_content();
		remove_filter( 'upload_dir', array( $this, 'broken_upload_dir' ), 20 );

		// With the uploads folder unavailable, no cover file can be found, so
		// every post is reported; the one that needed a cover is among them.
		$this->assertSame( 'failed', $failed['status'] );
		$this->assertStringContainsString( sprintf( '(ID %d) is missing a featured image', $no_cover ), implode( ' ', $failed['problems'] ) );
		$this->assertFalse( has_post_thumbnail( $no_cover ) );
		$this->assertFalse( get_option( WMPGF_Seeder::LOCK_OPTION ), 'The lock is released.' );

		// Run again once the uploads folder works.
		$retry = ( new WMPGF_Seeder() )->repair_demo_content();
		$this->assertSame( 'repaired', $retry['status'] );
		$this->assertTrue( $this->has_cover_file( $no_cover ) );
	}

	public function test_a_run_in_progress_is_not_repeated_but_a_dead_one_is_taken_over() {
		$this->legacy_install();

		// Another request is working on the demo content right now.
		add_option( WMPGF_Seeder::LOCK_OPTION, time(), '', false );
		$this->update_in_place();
		$this->assertFalse( get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$retry = wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK );
		$this->assertGreaterThanOrEqual( time() + WMPGF_Seeder::RETRY_DELAY - 60, $retry, 'Retried an hour later, not on every request.' );
		$this->assertSame( 'busy', ( new WMPGF_Seeder() )->repair_demo_content()['status'] );

		// That request died long ago.
		update_option( WMPGF_Seeder::LOCK_OPTION, time() - WMPGF_Seeder::LOCK_TIMEOUT - 60, false );
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );
		do_action( WMPGF_Seeder::VALIDATION_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own hook, through a constant.

		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
		$this->assertFalse( get_option( WMPGF_Seeder::LOCK_OPTION ), 'The lock is released.' );
		$this->assertFalse( wp_next_scheduled( WMPGF_Seeder::VALIDATION_HOOK ) );
	}

	public function test_reactivation_runs_the_same_check_and_keeps_removals() {
		list( , $no_terms ) = $this->legacy_install();
		$this->remove_directly( $no_terms );

		WMPGF_Plugin::activate();

		$this->assertSame( array(), $this->term_names( $no_terms, WMPGF_Post_Type::TAX_TAG ) );
		$this->assertFalse( has_post_thumbnail( $no_terms ) );
		$this->assertSame( WMPGF_Seeder::VALIDATION_VERSION, (int) get_option( WMPGF_Seeder::VALIDATED_VERSION_OPTION ) );
	}

	public function test_the_repair_waits_for_seeding_to_finish() {
		$this->assertSame( 'not-seeded', ( new WMPGF_Seeder() )->repair_demo_content()['status'] );
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
