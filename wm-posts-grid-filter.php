<?php
/**
 * Plugin Name:       Posts Grid + Filter
 * Description:       Dynamic Posts Grid and Posts Filter Gutenberg blocks, synced via the Interactivity API, with demo content seeded automatically on activation.
 * Version:           2.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Rotem Yagel
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wm-posts-grid-filter
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WMPGF_VERSION', '2.1.0' );
define( 'WMPGF_FILE', __FILE__ );
define( 'WMPGF_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMPGF_URL', plugin_dir_url( __FILE__ ) );

require_once WMPGF_DIR . 'includes/class-wmpgf-post-type.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-seeder.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-request.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-query.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-image-loading.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-blocks.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-single-template.php';
require_once WMPGF_DIR . 'includes/class-wmpgf-plugin.php';

/**
 * Boots the plugin. Kept as a single accessor so activation/deactivation
 * hooks and the runtime bootstrap all go through the same instance.
 */
function wmpgf_plugin() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new WMPGF_Plugin();
	}

	return $plugin;
}

register_activation_hook( __FILE__, array( 'WMPGF_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WMPGF_Plugin', 'deactivate' ) );

wmpgf_plugin()->init();
