/**
 * The editor preview (grid-preview.js) and the frontend (render.php) are
 * two templates for one card. These tests fail if they stop using the
 * same classes, which is what style.css depends on.
 */
import { readFileSync } from 'fs';
import { join } from 'path';
import { createRoot, flushSync } from '@wordpress/element';
import GridPreview from '../grid-preview';
import GridSkeleton from '../grid-skeleton';

const card = {
	id: 1,
	link: 'https://example.test/grid-post/one/',
	title: 'Tokens & <Types>',
	excerpt: 'An excerpt.',
	imageUrl: 'https://example.test/cover.svg',
	category: 'Design',
};

/**
 * Renders with React itself (the preview uses hooks), into a detached
 * element, synchronously.
 *
 * @param {Element} element Element to render.
 * @return {HTMLElement} Container holding the markup.
 */
const mount = ( element ) => {
	const container = document.createElement( 'div' );
	flushSync( () => createRoot( container ).render( element ) );
	return container;
};

const render = ( props ) => mount( <GridPreview columns={ 3 } { ...props } /> );

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
			[ ...article.children ].map( ( child ) => child.classList[ 0 ] )
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

	it( 'loads the first row right away and later rows lazily', () => {
		const cards = [ 1, 2, 3, 4 ].map( ( id ) => ( {
			...card,
			id,
			imageWidth: 300,
			imageHeight: 200,
		} ) );
		const images = [
			...render( { cards, columns: 3 } ).querySelectorAll( 'img' ),
		];

		expect(
			images.map( ( img ) => img.getAttribute( 'loading' ) )
		).toEqual( [ 'eager', 'eager', 'eager', 'lazy' ] );
		images.forEach( ( img ) => {
			expect( img.getAttribute( 'decoding' ) ).toBe( 'async' );
			expect( img.getAttribute( 'width' ) ).toBe( '300' );
			expect( img.getAttribute( 'height' ) ).toBe( '200' );
		} );
	} );

	it( 'shows a placeholder until an image has loaded', () => {
		const thumb = render( { cards: [ card ] } ).querySelector(
			'.wmpgf-grid__thumb'
		);

		expect( thumb.classList.contains( 'is-loading' ) ).toBe( true );
	} );

	it( 'sets the column class', () => {
		const grid = render( { cards: [ card ], columns: 4 } ).firstChild;

		expect( grid.className ).toBe( 'wmpgf-grid wmpgf-grid--cols-4' );
	} );
} );

describe( 'GridSkeleton', () => {
	const skeleton = ( props ) =>
		mount( <GridSkeleton { ...props } /> ).firstChild;

	it( 'has one placeholder per post on the page, in the same columns', () => {
		const grid = skeleton( { columns: 4, count: 8 } );

		expect( grid.className ).toBe( 'wmpgf-grid wmpgf-grid--cols-4' );
		expect(
			grid.querySelectorAll( '.wmpgf-grid__card.is-skeleton' )
		).toHaveLength( 8 );
	} );

	it( 'is hidden from screen readers and has no links', () => {
		const grid = skeleton( { columns: 3, count: 3 } );

		expect( grid.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( grid.querySelectorAll( 'a, img' ) ).toHaveLength( 0 );
	} );
} );
