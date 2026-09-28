<?php
/**
 * Plugin bootstrap: runtime hooks plus the activation/deactivation lifecycle.
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PGF_Plugin
 */
class PGF_Plugin {

	/**
	 * Plugin version the rewrite rules were last flushed for, so an already
	 * active install picks up permalink changes without a manual re-save.
	 */
	const REWRITE_VERSION_OPTION = 'pgf_rewrite_version';

	/**
	 * Registers the hooks needed on every normal request.
	 */
	public function init() {
		$post_type = new PGF_Post_Type();
		$post_type->init();

		$blocks = new PGF_Blocks();
		$blocks->init();

		$single_template = new PGF_Single_Template();
		$single_template->init();

		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
	}

	/**
	 * Flushes rewrite rules once per version. Runs at priority 20, after the
	 * post type and taxonomies are registered, so it compiles current rules.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( self::REWRITE_VERSION_OPTION ) === PGF_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::REWRITE_VERSION_OPTION, PGF_VERSION );
	}

	/**
	 * Activation runs before 'init', so the post type and taxonomies are
	 * registered here directly before flushing and seeding.
	 */
	public static function activate() {
		$post_type = new PGF_Post_Type();
		$post_type->register_taxonomies();
		$post_type->register_post_type();

		flush_rewrite_rules();
		update_option( self::REWRITE_VERSION_OPTION, PGF_VERSION );

		$seeder = new PGF_Seeder();
		$seeder->seed();
	}

	/**
	 * Keeps seeded content so reactivation doesn't reseed; uninstall.php
	 * does the actual removal.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
