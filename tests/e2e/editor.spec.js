/**
 * The blocks in the editor. Nothing is saved: the page is closed with its
 * changes discarded.
 */
import { test, expect } from '@playwright/test';
import { STORAGE_STATE } from './playwright.config';
import { demoPage } from './demo-page';

test.use( { storageState: STORAGE_STATE } );

/**
 * The block canvas: an iframe in current WordPress, the page itself in
 * older setups.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
const canvas = async ( page ) =>
	( await page.locator( 'iframe[name="editor-canvas"]' ).count() )
		? page.frameLocator( 'iframe[name="editor-canvas"]' )
		: page;

test.describe( 'Blocks in the editor', () => {
	// The first editor load on a fresh site builds its caches and can take
	// most of the default 30 seconds; later loads take about four.
	test.describe.configure( { timeout: 60 * 1000 } );

	test.beforeEach( async ( { page, request } ) => {
		const { id } = await demoPage( request );
		await page.goto( `wp-admin/post.php?post=${ id }&action=edit` );
		await page.waitForFunction(
			() =>
				window.wp?.data?.select( 'core/block-editor' ).getBlocks()
					.length > 0
		);
		// The first-visit welcome guide covers the editor.
		await page.evaluate( () =>
			window.wp.data
				.dispatch( 'core/preferences' )
				.set( 'core/edit-post', 'welcomeGuide', false )
		);
	} );

	test( 'the grid preview is React, with real posts', async ( { page } ) => {
		const grid = ( await canvas( page ) ).locator(
			'.wp-block-wmpgf-posts-grid'
		);

		await expect( grid.locator( '.wmpgf-grid__card' ) ).toHaveCount( 6 );
		await expect( grid.locator( '.wmpgf-grid__thumb img' ) ).toHaveCount(
			6
		);
		await expect( grid.locator( '.wmpgf-grid__category' ) ).toHaveCount(
			6
		);
		await expect(
			grid.locator( '.components-server-side-render' )
		).toHaveCount( 0 );
	} );

	test( 'the inspector controls change the preview', async ( { page } ) => {
		await page.evaluate( () => {
			const { select, dispatch } = window.wp.data;
			const grid = select( 'core/block-editor' )
				.getBlocks()
				.find( ( block ) => block.name === 'wmpgf/posts-grid' );
			dispatch( 'core/block-editor' ).selectBlock( grid.clientId );
			// Opens the settings sidebar on the Block tab.
			dispatch( 'core/interface' ).enableComplementaryArea(
				'core',
				'edit-post/block'
			);
		} );
		const grid = ( await canvas( page ) ).locator(
			'.wp-block-wmpgf-posts-grid'
		);
		await page.getByRole( 'spinbutton', { name: 'Columns' } ).fill( '2' );
		await expect( grid.locator( '.wmpgf-grid' ) ).toHaveClass(
			/wmpgf-grid--cols-2/
		);

		await page
			.getByRole( 'spinbutton', { name: 'Posts per page' } )
			.fill( '3' );
		await expect( grid.locator( '.wmpgf-grid__card' ) ).toHaveCount( 3 );
	} );

	test( 'the light/dark switch can be turned off per filter', async ( {
		page,
	} ) => {
		await page.evaluate( () => {
			const { select, dispatch } = window.wp.data;
			const filter = select( 'core/block-editor' )
				.getBlocks()
				.find( ( block ) => block.name === 'wmpgf/posts-filter' );
			dispatch( 'core/block-editor' ).selectBlock( filter.clientId );
			dispatch( 'core/interface' ).enableComplementaryArea(
				'core',
				'edit-post/block'
			);
		} );
		const preview = ( await canvas( page ) ).locator(
			'.wp-block-wmpgf-posts-filter .wmpgf-filter__scheme'
		);
		const setting = page.getByRole( 'checkbox', {
			name: 'Show light/dark switch',
		} );

		await expect( preview ).toHaveCount( 1 );
		await expect( setting ).toBeChecked();
		await setting.click();
		await expect( preview ).toHaveCount( 0 );
	} );

	test( 'a new grid always contains its locked pagination', async ( {
		page,
	} ) => {
		const clientId = await page.evaluate( () => {
			const grid = window.wp.blocks.createBlock( 'wmpgf/posts-grid' );
			window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( grid );
			return grid.clientId;
		} );

		// The template is applied once the new block renders.
		await expect
			.poll( () =>
				page.evaluate( ( id ) => {
					const editor = window.wp.data.select( 'core/block-editor' );
					const inner = editor.getBlocks( id );
					return {
						inner: inner.map( ( block ) => block.name ),
						lock: editor.getTemplateLock( id ),
						canRemove: editor.canRemoveBlock(
							inner[ 0 ]?.clientId
						),
						paginationAtRoot:
							editor.canInsertBlockType( 'wmpgf/pagination' ),
					};
				}, clientId )
			)
			.toEqual( {
				inner: [ 'wmpgf/pagination' ],
				lock: 'all',
				canRemove: false,
				paginationAtRoot: false,
			} );
	} );
} );
