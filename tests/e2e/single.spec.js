/**
 * A single Grid Post, as a visitor opens it from a card.
 */
import { test, expect } from '@playwright/test';
import { gridPost } from './demo-page';

test( 'a single post is a complete page with the theme’s header and footer', async ( {
	page,
	request,
} ) => {
	const link = await gridPost( request );
	const response = await request.get( link );
	const html = await response.text();

	// Block themes have no header.php: the PHP template's get_header() used
	// to print a deprecation notice, no doctype and no viewport tag.
	expect( html.trimStart() ).toMatch( /^<!DOCTYPE html>/i );
	expect( html ).toMatch( /<meta name="viewport"[^>]*width=device-width/ );
	expect( html ).not.toMatch( /Deprecated|Notice:|Warning:/ );

	await page.goto( link );
	await expect(
		page.locator( 'header.wp-block-template-part' ).first()
	).toBeVisible();
	await expect(
		page.locator( 'footer.wp-block-template-part' ).first()
	).toBeAttached();
	await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
	await expect( page.locator( 'main img' ).first() ).toBeVisible();
} );
