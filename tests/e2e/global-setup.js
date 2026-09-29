/**
 * Logs in once and saves the session for the editor tests. The frontend
 * tests run as an anonymous visitor.
 */
import { chromium } from '@playwright/test';
import { STORAGE_STATE } from './playwright.config';

export default async function globalSetup( config ) {
	const { baseURL, channel } = config.projects[ 0 ].use;
	const browser = await chromium.launch( { channel } );
	const page = await browser.newPage( { baseURL } );

	await page.goto( 'wp-login.php' );
	await page.fill( '#user_login', process.env.WP_USERNAME || 'admin' );
	await page.fill( '#user_pass', process.env.WP_PASSWORD || 'password' );
	await Promise.all( [
		page.waitForURL( /\/wp-admin\// ),
		page.click( '#wp-submit' ),
	] );

	await page.context().storageState( { path: STORAGE_STATE } );
	await browser.close();
}
