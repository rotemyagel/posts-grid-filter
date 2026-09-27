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
	 * Option storing the plugin version rewrite rules were last flushed
	 * for. Bumped alongside PGF_VERSION whenever a change actually alters
	 * generated rewrite rules (e.g. the %pgf_category% permalink structure),
	 * so an already-active install picks up the change on its next request
	 * without every site owner needing to know to visit Settings ->
	 * Permalinks by hand -- the standard, well-known gotcha with changing a
	 * post type's rewrite structure after it has already been activated.
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
	 * Flushes rewrite rules once per version bump, only when they're
	 * actually stale. Hooked at priority 20, after register_post_type()/
	 * register_taxonomies() (both default priority 10) have already run on
	 * this same 'init', so the flush -- if one happens -- compiles the
	 * already-current, already-registered rules rather than racing them.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( self::REWRITE_VERSION_OPTION ) === PGF_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::REWRITE_VERSION_OPTION, PGF_VERSION );
	}

	/**
	 * Fires on plugin activation. Registers the post type/taxonomies
	 * immediately (activation runs before the 'init' hook in the same
	 * request) so the seeder has somewhere to insert content, then flushes
	 * rewrite rules and seeds demo content.
	 */
	public static function activate() {
		$post_type = new PGF_Post_Type();
		$post_type->register_rewrite_tag();
		$post_type->register_taxonomies();
		$post_type->register_post_type();

		flush_rewrite_rules();
		update_option( self::REWRITE_VERSION_OPTION, PGF_VERSION );

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
