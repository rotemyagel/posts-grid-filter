/**
 * urlWith() must build the same URLs as WMPGF_Request::url() in PHP
 * (tests/php/test-url.php covers that side).
 */
import { urlWith } from '../url';

const params = {
	page: 'wmpgf-page',
	categories: 'wmpgf-category',
	tags: 'wmpgf-tag',
	search: 'wmpgf-search',
	perPage: 'wmpgf-per-page',
};

const at = ( url ) => window.history.replaceState( null, '', url );

describe( 'urlWith', () => {
	it( 'keeps unrelated params exactly as they were', () => {
		at( '/demo/?utm_source=a%2Cb&wmpgf-category=design,culture' );

		expect( urlWith( { page: 2 }, params ) ).toBe(
			'/demo/?utm_source=a%2Cb&wmpgf-category=design,culture&wmpgf-page=2'
		);
	} );

	it( 'replaces its own params in every form a URL can carry them', () => {
		at(
			'/demo/?wmpgf-category[]=a&wmpgf-category%5B%5D=b&wmpgf-category%5b0%5d=c&wmpgf-category=d&keep=1'
		);

		expect( urlWith( { categories: [ 'design' ] }, params ) ).toBe(
			'/demo/?keep=1&wmpgf-category=design'
		);
	} );

	it( 'joins several slugs with commas, unencoded', () => {
		at( '/demo/' );

		expect(
			urlWith( { categories: [ 'design', 'culture' ] }, params )
		).toBe( '/demo/?wmpgf-category=design,culture' );
	} );

	it( 'omits page 1 and resets the page when filters change', () => {
		at( '/demo/?wmpgf-page=3' );

		expect( urlWith( { page: 1 }, params ) ).toBe( '/demo/' );
		expect( urlWith( { tags: [ 'news' ] }, params ) ).toBe(
			'/demo/?wmpgf-tag=news'
		);
	} );

	it( 'drops a param set to an empty value', () => {
		at( '/demo/?wmpgf-category=design&wmpgf-search=x' );

		expect( urlWith( { categories: [], search: '' }, params ) ).toBe(
			'/demo/'
		);
	} );

	it( 'encodes the search term', () => {
		at( '/' );

		expect( urlWith( { search: 'a&b c=d' }, params ) ).toBe(
			'/?wmpgf-search=a%26b%20c%3Dd'
		);
	} );

	it( 'writes the page size as a number', () => {
		at( '/demo/?wmpgf-page=2' );

		expect( urlWith( { perPage: '12' }, params ) ).toBe(
			'/demo/?wmpgf-per-page=12'
		);
	} );
} );
