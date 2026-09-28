<?php
/**
 * Plugin Name:       Posts Grid + Filter
 * Description:       Dynamic Posts Grid and Posts Filter Gutenberg blocks, synced via the Interactivity API, with demo content seeded automatically on activation.
 * Version:           1.3.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Rotem Yagel
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wm-posts-grid-filter
 *
 * @package PostsGridFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'PGF_VERSION', '1.3.2' );
define( 'PGF_FILE', __FILE__ );
define( 'PGF_DIR', plugin_dir_path( __FILE__ ) );
define( 'PGF_URL', plugin_dir_url( __FILE__ ) );

require_once PGF_DIR . 'includes/class-pgf-post-type.php';
require_once PGF_DIR . 'includes/class-pgf-seeder.php';
require_once PGF_DIR . 'includes/class-pgf-blocks.php';
require_once PGF_DIR . 'includes/class-pgf-single-template.php';
require_once PGF_DIR . 'includes/class-pgf-plugin.php';

/**
 * Boots the plugin. Kept as a single accessor so activation/deactivation
 * hooks and the runtime bootstrap all go through the same instance.
 */
function pgf_plugin() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new PGF_Plugin();
	}

	return $plugin;
}

register_activation_hook( __FILE__, array( 'PGF_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PGF_Plugin', 'deactivate' ) );

pgf_plugin()->init();
