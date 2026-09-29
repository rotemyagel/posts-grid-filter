/**
 * Browser tests against a running WordPress with the plugin active and its
 * demo content seeded: wp-env in CI (http://localhost:8888, admin/password),
 * or any disposable site through WP_BASE_URL, WP_USERNAME and WP_PASSWORD.
 * They never save content, so they can run against the same site again.
 */
import { defineConfig } from '@playwright/test';

export const STORAGE_STATE = `${ __dirname }/.auth/admin.json`;

// Paths in the tests are relative, so the site can live in a subfolder.
const baseURL = ( process.env.WP_BASE_URL || 'http://localhost:8888' ).replace(
	/\/?$/,
	'/'
);

export default defineConfig( {
	testDir: '.',
	outputDir: '../../test-results',
	timeout: 30 * 1000,
	expect: { timeout: 10 * 1000 },
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : 'list',
	globalSetup: require.resolve( './global-setup.js' ),
	use: {
		baseURL,
		// PW_CHANNEL=chrome uses an installed Chrome instead of downloading one.
		channel: process.env.PW_CHANNEL || undefined,
		trace: 'retain-on-failure',
	},
} );
