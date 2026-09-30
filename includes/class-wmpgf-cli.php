<?php
/**
 * WP-CLI commands. Loaded only when WP-CLI runs.
 *
 * @package WMPGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin's demo content.
 */
class WMPGF_CLI {

	/**
	 * Restores missing featured images and terms on the plugin's own demo posts.
	 *
	 * Runs only when you ask: a demo post missing its image or a term may be
	 * a failed seed by version 2.0.0, or your own change, and the plugin
	 * can't tell which. Only published demo posts on the plugin's ownership
	 * records are changed. Deleted posts and terms aren't recreated, drafted
	 * or trashed posts stay as they are, and extra terms and edited text are
	 * kept.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List what would be restored, and write nothing to the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wmpgf repair-demo-content --dry-run
	 *     wp wmpgf repair-demo-content
	 *
	 * @subcommand repair-demo-content
	 *
	 * @param array $args       Positional arguments (none).
	 * @param array $assoc_args Flags.
	 */
	public function repair_demo_content( $args, $assoc_args ) {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$result  = ( new WMPGF_Seeder() )->repair_demo_content( $dry_run );

		foreach ( $result['skipped'] as $line ) {
			WP_CLI::log( 'Left alone: ' . $line );
		}
		foreach ( $result['changes'] as $line ) {
			WP_CLI::log( ( $dry_run ? 'Would restore: ' : 'Restoring: ' ) . $line );
		}

		switch ( $result['status'] ) {
			case 'not-seeded':
				WP_CLI::error( 'Seeding has not finished yet. Deactivate and activate the plugin to finish it.' );
				break;
			case 'busy':
				WP_CLI::error( 'Another run is working on the demo content. Try again in a few minutes.' );
				break;
			case 'failed':
				foreach ( $result['problems'] as $line ) {
					WP_CLI::warning( 'Still missing: ' . $line );
				}
				WP_CLI::error( 'Some demo content could not be restored; see WP_DEBUG_LOG for why. Run the command again once that is fixed.' );
				break;
			case 'planned':
				WP_CLI::success( 'Dry run: nothing was changed.' );
				break;
			case 'repaired':
				WP_CLI::success( 'Demo content restored.' );
				break;
			default:
				WP_CLI::success( 'Nothing to restore.' );
		}
	}
}
