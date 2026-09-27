<?php
/**
 * Plugin bootstrap: wires runtime hooks and owns the activation/deactivation
 * lifecycle.
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
	 * Registers the hooks needed on every normal request.
	 */
	public function init() {
		$post_type = new PGF_Post_Type();
		$post_type->init();

		$blocks = new PGF_Blocks();
		$blocks->init();

		$single_template = new PGF_Single_Template();
		$single_template->init();
	}

	/**
	 * Fires on plugin activation. Registers the post type/taxonomies
	 * immediately (activation runs before the 'init' hook in the same
	 * request) so the seeder has somewhere to insert content, then flushes
	 * rewrite rules and seeds demo content.
	 */
	public static function activate() {
		$post_type = new PGF_Post_Type();
		$post_type->register_post_type();
		$post_type->register_taxonomies();

		flush_rewrite_rules();

		$seeder = new PGF_Seeder();
		$seeder->seed();
	}

	/**
	 * Fires on plugin deactivation. Only flushes rewrite rules; seeded
	 * content is left in place so reactivating does not need to reseed.
	 * Full removal happens in uninstall.php.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
