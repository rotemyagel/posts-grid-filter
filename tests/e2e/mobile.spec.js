/**
 * The demo page on a 375 x 812 phone, in light and dark mode.
 */
import { test, expect } from '@playwright/test';
import { demoPage } from './demo-page';

test.use( { viewport: { width: 375, height: 812 }, hasTouch: true } );

let link;

test.beforeAll( async ( { request } ) => {
	( { link } = await demoPage( request ) );
} );

const box = ( locator ) => locator.first().boundingBox();

for ( const colorScheme of [ 'light', 'dark' ] ) {
	test.describe( `${ colorScheme } mode`, () => {
		test.beforeEach( async ( { page } ) => {
			await page.emulateMedia( { colorScheme } );
			await page.goto( link );
		} );

		test( 'the blocks use the full content width, with no sideways scroll', async ( {
			page,
		} ) => {
			expect(
				await page.evaluate(
					() => document.documentElement.scrollWidth
				)
			).toBe( 375 );

			// No padding of their own: cards are as wide as the theme's
			// content area, and line up with the page title.
			const card = await box( page.locator( '.wmpgf-grid__card' ) );
			const title = await box( page.locator( 'h1' ) );
			expect( card.width ).toBeGreaterThanOrEqual( 300 );
			expect( Math.round( card.x ) ).toBe( Math.round( title.x ) );
		} );

		test( 'the filter stacks into rows, and both groups are in view', async ( {
			page,
		} ) => {
			const search = page.locator( '.wmpgf-filter__search' );
			const categories = page
				.locator( '.wmpgf-filter__options' )
				.nth( 0 );
			const tags = page.locator( '.wmpgf-filter__options' ).nth( 1 );

			// Row 1: search, full width, big enough that iOS doesn't zoom.
			expect( ( await box( search ) ).width ).toBeGreaterThanOrEqual(
				250
			);
			expect(
				parseFloat(
					await search.evaluate(
						( input ) => window.getComputedStyle( input ).fontSize
					)
				)
			).toBeGreaterThanOrEqual( 16 );

			// Row 2: the count below the search.
			const count = await box( page.locator( '.wmpgf-filter__count' ) );
			expect( count.y ).toBeGreaterThan( ( await box( search ) ).y );

			// Rows 3 and 4: categories, then tags, each visible without scrolling.
			expect( ( await box( tags ) ).y ).toBeGreaterThan(
				( await box( categories ) ).y
			);
			await expect(
				tags.locator( '.wmpgf-filter__option' ).first()
			).toBeInViewport();
			expect( await tags.evaluate( ( row ) => row.scrollLeft ) ).toBe(
				0
			);

			// Each row scrolls on its own, with no scrollbar showing.
			for ( const row of [ categories, tags ] ) {
				expect(
					await row.evaluate( ( el ) => ( {
						overflow: window.getComputedStyle( el ).overflowX,
						scrollbar: window.getComputedStyle( el ).scrollbarWidth,
						scrolls: el.scrollWidth > el.clientWidth,
					} ) )
				).toEqual( {
					overflow: 'auto',
					scrollbar: 'none',
					scrolls: true,
				} );
			}
		} );

		test( 'the grid starts 16px below the filter', async ( { page } ) => {
			const filter = await box(
				page.locator( '.wp-block-wmpgf-posts-filter' )
			);
			const card = await box( page.locator( '.wmpgf-grid__card' ) );

			expect( Math.round( card.y - ( filter.y + filter.height ) ) ).toBe(
				16
			);
		} );

		test( 'pagination: the page links on one row, the page size below', async ( {
			page,
		} ) => {
			const nav = await box( page.locator( '.wmpgf-pagination__nav' ) );
			const perPage = await box(
				page.locator( '.wmpgf-pagination__per-page' )
			);
			const next = await box( page.locator( '.wmpgf-pagination__next' ) );
			const prev = await box( page.locator( '.wmpgf-pagination__prev' ) );

			expect( perPage.y ).toBeGreaterThanOrEqual( nav.y + nav.height );
			// Both rows are centred.
			for ( const row of [ nav, perPage ] ) {
				expect(
					Math.abs( row.x + row.width / 2 - 375 / 2 )
				).toBeLessThanOrEqual( 1 );
			}
			expect( Math.abs( prev.y - next.y ) ).toBeLessThanOrEqual( 1 );
			expect( prev.height ).toBeGreaterThanOrEqual( 44 );
			expect( next.height ).toBeGreaterThanOrEqual( 44 );
		} );
	} );
}

test( 'a pill selected in the URL is scrolled into view in its row', async ( {
	page,
	request,
} ) => {
	const response = await request.get(
		'?rest_route=/wp/v2/wmpgf_tag&per_page=100&_fields=slug,name&orderby=name'
	);
	const tags = await response.json();
	const last = tags[ tags.length - 1 ];
	await page.goto(
		`${ link }${ link.includes( '?' ) ? '&' : '?' }wmpgf-tag=${ last.slug }`
	);

	const pill = page
		.locator( '.wmpgf-filter__option' )
		.filter( { hasText: new RegExp( `^\\s*${ last.name }\\s*$` ) } );
	await expect( pill ).toBeInViewport( { ratio: 1 } );
} );

test( 'pills keep a 44px touch target', async ( { page } ) => {
	await page.goto( link );
	const target = await page
		.locator( '.wmpgf-filter__option' )
		.first()
		.evaluate( ( pill ) => {
			const after = window.getComputedStyle( pill, '::after' );
			return (
				pill.getBoundingClientRect().height -
				parseFloat( after.top ) -
				parseFloat( after.bottom )
			);
		} );
	expect( target ).toBeGreaterThanOrEqual( 44 );
} );

test( 'nothing moves while the page loads and the scripts start', async ( {
	page,
} ) => {
	await page.addInitScript( () => {
		window.wmpgfShift = 0;
		new window.PerformanceObserver( ( list ) => {
			for ( const entry of list.getEntries() ) {
				if ( ! entry.hadRecentInput ) {
					window.wmpgfShift += entry.value;
				}
			}
		} ).observe( { type: 'layout-shift', buffered: true } );
	} );
	await page.goto( link );
	// The script shows the light/dark switch; the font and images arrive.
	await expect( page.locator( '.wmpgf-filter__scheme' ) ).toBeVisible();
	await page.waitForLoadState( 'load' );
	await page.evaluate( () => document.fonts.ready );

	expect( await page.evaluate( () => window.wmpgfShift ) ).toBeLessThan(
		0.01
	);
} );
