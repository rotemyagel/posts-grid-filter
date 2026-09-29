<?php
/**
 * Plugin bootstrap: runtime hooks plus the activation/deactivation lifecycle.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WMPGF_Plugin
 */
class WMPGF_Plugin {

	/**
	 * Plugin version the rewrite rules were last flushed for, so an already
	 * active install picks up permalink changes without a manual re-save.
	 */
	const REWRITE_VERSION_OPTION = 'wmpgf_rewrite_version';

	/**
	 * Registers the hooks needed on every normal request.
	 */
	public function init() {
		$post_type = new WMPGF_Post_Type();
		$post_type->init();

		WMPGF_Query::init();

		$blocks = new WMPGF_Blocks();
		$blocks->init();

		$single_template = new WMPGF_Single_Template();
		$single_template->init();

		$meta_description = new WMPGF_Meta_Description();
		$meta_description->init();

		$image_loading = new WMPGF_Image_Loading();
		$image_loading->init();

		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );

		// Activation doesn't run when the plugin is updated in place, so an
		// install seeded by an earlier version is checked from here, in the
		// background, once. See WMPGF_Seeder::seed().
		add_action( 'init', array( 'WMPGF_Seeder', 'schedule_validation' ), 20 );
		add_action( WMPGF_Seeder::VALIDATION_HOOK, array( 'WMPGF_Seeder', 'run_scheduled_validation' ) );
	}

	/**
	 * Flushes rewrite rules once per version. Runs at priority 20, after the
	 * post type and taxonomies are registered, so it compiles current rules.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( self::REWRITE_VERSION_OPTION ) === WMPGF_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::REWRITE_VERSION_OPTION, WMPGF_VERSION );
	}

	/**
	 * Activation runs before 'init', so the post type and taxonomies are
	 * registered here directly before flushing and seeding.
	 */
	public static function activate() {
		$post_type = new WMPGF_Post_Type();
		$post_type->register_taxonomies();
		$post_type->register_post_type();

		flush_rewrite_rules();
		update_option( self::REWRITE_VERSION_OPTION, WMPGF_VERSION );

		$seeder = new WMPGF_Seeder();
		$seeder->seed();
	}

	/**
	 * Keeps seeded content so reactivation doesn't reseed; uninstall.php
	 * does the actual removal.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( WMPGF_Seeder::VALIDATION_HOOK );
	}
}
