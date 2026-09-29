/**
 * The editor preview (grid-preview.js) and the frontend (render.php) are
 * two templates for one card. These tests fail if they stop using the
 * same classes, which is what style.css depends on.
 */
import { readFileSync } from 'fs';
import { join } from 'path';
import { renderToString } from '@wordpress/element';
import GridPreview from '../grid-preview';

const card = {
	id: 1,
	link: 'https://example.test/grid-post/one/',
	title: 'Tokens & <Types>',
	excerpt: 'An excerpt.',
	imageUrl: 'https://example.test/cover.svg',
	category: 'Design',
};

const render = ( props ) => {
	const html = renderToString( <GridPreview columns={ 3 } { ...props } /> );
	return new window.DOMParser().parseFromString( html, 'text/html' ).body;
};

const gridClasses = ( text ) => new Set( text.match( /wmpgf-grid__[a-z-]+/g ) );

describe( 'GridPreview', () => {
	it( 'uses exactly the classes render.php uses', () => {
		const php = readFileSync( join( __dirname, '../render.php' ), 'utf8' );
		const preview =
			render( { cards: [ card ] } ).innerHTML +
			render( { cards: [] } ).innerHTML;

		expect( [ ...gridClasses( preview ) ].sort() ).toEqual(
			[ ...gridClasses( php ) ].sort()
		);
	} );

	it( 'renders a card in the same order as render.php', () => {
		const article = render( { cards: [ card ] } ).querySelector(
			'article.wmpgf-grid__card'
		);

		expect(
			[ ...article.children ].map( ( child ) => child.className )
		).toEqual( [
			'wmpgf-grid__thumb',
			'wmpgf-grid__category',
			'wmpgf-grid__title',
			'wmpgf-grid__excerpt',
		] );
		expect( article.querySelector( 'img' ).getAttribute( 'alt' ) ).toBe(
			''
		);
		expect( article.querySelector( 'a' ).getAttribute( 'href' ) ).toBe(
			card.link
		);
	} );

	it( 'shows titles as text, never as HTML', () => {
		const title = render( { cards: [ card ] } ).querySelector(
			'.wmpgf-grid__title a'
		);

		expect( title.textContent ).toBe( 'Tokens & <Types>' );
		expect( title.children ).toHaveLength( 0 );
	} );

	it( 'leaves out the image and label when a post has none', () => {
		const article = render( {
			cards: [ { ...card, imageUrl: '', category: '' } ],
		} ).querySelector( 'article' );

		expect( article.querySelector( '.wmpgf-grid__thumb' ) ).toBeNull();
		expect( article.querySelector( '.wmpgf-grid__category' ) ).toBeNull();
	} );

	it( 'sets the column class', () => {
		const grid = render( { cards: [ card ], columns: 4 } ).firstChild;

		expect( grid.className ).toBe( 'wmpgf-grid wmpgf-grid--cols-4' );
	} );
} );
