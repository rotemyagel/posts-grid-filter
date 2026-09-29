/**
 * The demo page as a visitor uses it. Counts come from the seeded demo
 * content (includes/demo-content.php).
 */
import { test, expect } from '@playwright/test';
import { demoPage } from './demo-page';

/**
 * Clicks a filter pill by its visible name, as a visitor would.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          name Term name.
 */
const pill = ( page, name ) =>
	page
		.locator( '.wmpgf-filter__option' )
		.filter( { hasText: new RegExp( `^\\s*${ name }\\s*$` ) } )
		.click();

const cards = ( page ) => page.locator( '.wmpgf-grid__card' );
const count = ( page ) => page.locator( '.wmpgf-filter__count' );

test.describe( 'Posts Grid + Filter on the demo page', () => {
	let link;

	test.beforeAll( async ( { request } ) => {
		( { link } = await demoPage( request ) );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( link );
		await expect( count( page ) ).toHaveText( '12 posts' );
		// Survives router navigation, lost on a full page load.
		await page.evaluate( () => ( window.wmpgfNoReload = true ) );
	} );

	test.afterEach( async ( { page } ) => {
		expect( await page.evaluate( () => window.wmpgfNoReload ) ).toBe(
			true
		);
	} );

	test( 'OR within categories, AND with tags, without a reload', async ( {
		page,
	} ) => {
		await pill( page, 'Business' );
		await expect( page ).toHaveURL( /wmpgf-category=business(&|$)/ );
		await expect( count( page ) ).toHaveText( '4 posts' );
		await expect( cards( page ) ).toHaveCount( 4 );

		await pill( page, 'Culture' );
		await expect( page ).toHaveURL( /wmpgf-category=business,culture/ );
		await expect( count( page ) ).toHaveText( '7 posts' );

		await pill( page, 'Guide' );
		await expect( page ).toHaveURL( /wmpgf-tag=guide/ );
		await expect( count( page ) ).toHaveText( '3 posts' );
		await expect( cards( page ) ).toHaveCount( 3 );
	} );

	test( 'Back and Forward step through filter changes', async ( {
		page,
	} ) => {
		await pill( page, 'Business' );
		await expect( count( page ) ).toHaveText( '4 posts' );
		await pill( page, 'Guide' );
		await expect( count( page ) ).toHaveText( '2 posts' );

		await page.goBack();
		await expect( count( page ) ).toHaveText( '4 posts' );
		await expect(
			page.getByRole( 'checkbox', { name: 'Guide' } )
		).not.toBeChecked();
		await expect(
			page.getByRole( 'checkbox', { name: 'Business' } )
		).toBeChecked();

		await page.goForward();
		await expect( count( page ) ).toHaveText( '2 posts' );
		await expect(
			page.getByRole( 'checkbox', { name: 'Guide' } )
		).toBeChecked();
	} );

	test( 'keyboard focus stays on the checkbox after filtering', async ( {
		page,
	} ) => {
		const design = page.getByRole( 'checkbox', { name: 'Design' } );
		await design.focus();
		await page.keyboard.press( 'Space' );

		await expect( count( page ) ).toHaveText( '4 posts' );
		await expect( design ).toBeFocused();
	} );

	test( 'pagination and page size keep working, focus moves to the new posts', async ( {
		page,
	} ) => {
		await page.getByRole( 'link', { name: /Next/ } ).click();
		await expect( page ).toHaveURL( /wmpgf-page=2/ );
		await expect( page.getByText( 'Page 2 of 2' ) ).toBeVisible();
		await expect(
			page.locator( '.wmpgf-grid__title a' ).first()
		).toBeFocused();

		await page
			.getByRole( 'combobox', { name: 'Posts per page' } )
			.selectOption( '12' );
		await expect( page ).toHaveURL( /wmpgf-per-page=12/ );
		await expect( page ).not.toHaveURL( /wmpgf-page=/ );
		await expect( cards( page ) ).toHaveCount( 12 );
		await expect( page.locator( '.wmpgf-pagination__nav' ) ).toHaveCount(
			0
		);
	} );

	test( 'search waits for a pause, then loads once', async ( { page } ) => {
		const searches = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'wmpgf-search=' ) ) {
				searches.push( request.url() );
			}
		} );

		await page
			.getByRole( 'searchbox' )
			.pressSequentially( 'color', { delay: 50 } );

		await expect( page ).toHaveURL( /wmpgf-search=color/ );
		await expect( count( page ) ).toHaveText( '1 post' );
		expect( searches ).toHaveLength( 1 );
	} );
} );

test( 'without JavaScript the filter is a working form', async ( {
	browser,
	request,
	baseURL,
} ) => {
	const { link } = await demoPage( request );
	const context = await browser.newContext( {
		baseURL,
		javaScriptEnabled: false,
	} );
	const page = await context.newPage();

	await page.goto( link );
	await pill( page, 'Business' );
	await page.getByRole( 'button', { name: 'Apply filters' } ).click();

	await expect( page ).toHaveURL( /wmpgf-category/ );
	await expect( count( page ) ).toHaveText( '4 posts' );
	await expect( cards( page ) ).toHaveCount( 4 );
	await context.close();
} );

test.describe( 'Light and dark mode', () => {
	const DARK = 'rgb(23, 25, 31)';
	const LIGHT = 'rgb(255, 255, 255)';
	const background = ( page ) =>
		page
			.locator( '.wp-block-wmpgf-posts-grid' )
			.evaluate(
				( grid ) => window.getComputedStyle( grid ).backgroundColor
			);

	test( 'follows the device, then remembers the choice', async ( {
		page,
		request,
	} ) => {
		const { link } = await demoPage( request );
		await page.emulateMedia( { colorScheme: 'dark' } );
		await page.goto( link );
		const toggle = page.getByRole( 'button', { name: 'Dark mode' } );

		await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
		expect( await background( page ) ).toBe( DARK );

		await toggle.click();
		await expect( toggle ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect.poll( () => background( page ) ).toBe( LIGHT );

		// Kept across a filter change and a reload, though the device is dark.
		await pill( page, 'Design' );
		await expect( count( page ) ).toHaveText( '4 posts' );
		expect( await background( page ) ).toBe( LIGHT );
		await page.reload();
		await expect( toggle ).toHaveAttribute( 'aria-pressed', 'false' );
		expect( await background( page ) ).toBe( LIGHT );
	} );

	test( 'a saved choice applies before the plugin’s scripts load', async ( {
		page,
		request,
	} ) => {
		const { link } = await demoPage( request );
		await page.goto( link );
		await page.getByRole( 'button', { name: 'Dark mode' } ).click();
		await expect.poll( () => background( page ) ).toBe( DARK );

		// With the view scripts blocked, only the inline script can apply it.
		await page.route( /\/build\/blocks\/.*view\.js/, ( route ) =>
			route.abort()
		);
		await page.reload();
		expect( await background( page ) ).toBe( DARK );
	} );

	test( 'without JavaScript the switch is hidden and the device decides', async ( {
		browser,
		request,
		baseURL,
	} ) => {
		const { link } = await demoPage( request );
		const context = await browser.newContext( {
			baseURL,
			javaScriptEnabled: false,
			colorScheme: 'dark',
		} );
		const page = await context.newPage();
		await page.goto( link );

		await expect( page.locator( '.wmpgf-filter__scheme' ) ).toBeHidden();
		expect( await background( page ) ).toBe( DARK );
		await context.close();
	} );
} );

test.describe( 'Loading states', () => {
	let link;

	test.beforeAll( async ( { request } ) => {
		( { link } = await demoPage( request ) );
	} );

	test( 'a slow page load shows a skeleton in place of the cards', async ( {
		page,
	} ) => {
		await page.goto( link );
		await page.route(
			( url ) => url.searchParams.get( 'wmpgf-category' ) === 'culture',
			async ( route ) => {
				await new Promise( ( resolve ) => setTimeout( resolve, 1500 ) );
				await route.continue();
			}
		);
		const grid = page.locator( '.wp-block-wmpgf-posts-grid' );

		await pill( page, 'Culture' );
		await expect( grid ).toHaveClass( /is-skeleton/ );
		await expect( grid ).toHaveAttribute( 'aria-busy', 'true' );

		await expect( count( page ) ).toHaveText( '4 posts' );
		await expect( grid ).not.toHaveClass( /is-skeleton/ );
		await expect( cards( page ) ).toHaveCount( 4 );
	} );

	test( 'the first image loads eagerly, later ones lazily', async ( {
		page,
	} ) => {
		await page.goto( link );
		const images = page.locator( '.wmpgf-grid__thumb img' );

		await expect( images.first() ).toHaveAttribute(
			'fetchpriority',
			'high'
		);
		await expect( images.first() ).not.toHaveAttribute( 'loading', 'lazy' );
		await expect( images.last() ).toHaveAttribute( 'loading', 'lazy' );
	} );

	test( 'an image shows a placeholder until it has loaded', async ( {
		page,
	} ) => {
		let release;
		const held = new Promise( ( resolve ) => ( release = resolve ) );
		await page.route( /wmpgf-cover-[^/]*\.svg/, async ( route ) => {
			await held;
			await route.continue();
		} );
		// Not waiting for "load": that would wait for the held images.
		await page.goto( link, { waitUntil: 'domcontentloaded' } );
		const thumb = page.locator( '.wmpgf-grid__thumb' ).first();

		await expect( thumb ).toHaveClass( /is-loading/ );
		release();
		await expect( thumb ).not.toHaveClass( /is-loading/ );
	} );
} );

test( 'on a phone, the pill row starts at its first label', async ( {
	page,
	request,
} ) => {
	const { link } = await demoPage( request );
	await page.setViewportSize( { width: 375, height: 812 } );
	await page.goto( link );
	const groups = page.locator( '.wmpgf-filter__groups' );

	// Scroll snapping used to jump to the first pill, hiding "Categories".
	await expect(
		groups.getByText( 'Categories', { exact: true } )
	).toBeInViewport();
	expect( await groups.evaluate( ( row ) => row.scrollLeft ) ).toBe( 0 );
} );
