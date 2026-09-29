/**
 * Automated accessibility checks (axe) on the pages the plugin renders, in
 * light and dark mode. Axe finds what can be found automatically, such as
 * contrast, names, roles and heading order; it doesn't replace testing
 * with a keyboard and a screen reader.
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { demoPage, gridPost } from './demo-page';

const pages = {
	'the demo page': async ( request ) => ( await demoPage( request ) ).link,
	'a single post': gridPost,
};

for ( const [ name, address ] of Object.entries( pages ) ) {
	for ( const colorScheme of [ 'light', 'dark' ] ) {
		test( `${ name } passes axe in ${ colorScheme } mode`, async ( {
			page,
			request,
		} ) => {
			await page.emulateMedia( { colorScheme } );
			await page.goto( await address( request ) );

			const { violations } = await new AxeBuilder( { page } )
				// Core's navigation block in the theme header nests its page
				// list's <ul> straight inside its own, on every page (see the
				// README's known limitations). The rest of the page is checked.
				.exclude( '.wp-block-navigation' )
				.withTags( [
					'wcag2a',
					'wcag2aa',
					'wcag21a',
					'wcag21aa',
					'wcag22aa',
					'best-practice',
				] )
				.analyze();

			expect(
				violations.map(
					( violation ) =>
						`${ violation.id }: ${ violation.nodes
							.map( ( node ) => node.target.join( ' ' ) )
							.join( ', ' ) }`
				)
			).toEqual( [] );
		} );
	}
}
