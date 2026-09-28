/**
 * Builds readable page URLs with the current selection, e.g.
 * `?wmpgf-category=design,culture&wmpgf-page=2`, for pagination hrefs and the
 * filter's address-bar sync. Written by hand rather than through
 * URLSearchParams, which would encode `,` as %2C.
 */

/**
 * Whether a raw query key is one of ours, in any form: `wmpgf-category`,
 * `wmpgf-category[]`, `wmpgf-category[0]`, or their %5B/%5D-encoded versions.
 *
 * @param {string}   rawKey Key as it appears in the query string.
 * @param {string[]} names  Our param names.
 * @return {boolean} True if the key should be replaced.
 */
const isOwnKey = ( rawKey, names ) => {
	const key = rawKey.replace( /%5B/gi, '[' ).replace( /%5D/gi, ']' );
	return names.some(
		( name ) => key === name || key.startsWith( `${ name }[` )
	);
};

/**
 * Joins values with commas into a query param, or nothing if none.
 *
 * @param {string}               name   Param name.
 * @param {Array<string|number>} values Term IDs or slugs.
 * @return {string[]} Zero or one `name=a,b` parts.
 */
export const listParam = ( name, values ) =>
	values?.length ? [ `${ name }=${ values.join( ',' ) }` ] : [];

/**
 * @param {Object}   args
 * @param {number}   args.page        Page number; 1 omits the page param.
 * @param {number[]} args.categories  Selected wmpgf_category term IDs.
 * @param {number[]} args.tags        Selected wmpgf_tag term IDs.
 * @param {Object}   [args.termSlugs] state.termSlugs: param => { id: slug }.
 * @param {Object}   [args.config]    state.config (param names), if present.
 * @return {string} Path and query for the current page.
 */
export const buildFilterUrl = ( {
	page,
	categories,
	tags,
	termSlugs,
	config,
} ) => {
	const pageParam = config?.pageParam || 'wmpgf-page';
	const categoryParam = config?.categoryParam || 'wmpgf-category';
	const tagParam = config?.tagParam || 'wmpgf-tag';

	// Other params are kept exactly as they were, not re-encoded.
	const parts = window.location.search
		.slice( 1 )
		.split( '&' )
		.filter(
			( part ) =>
				part &&
				! isOwnKey( part.split( '=' )[ 0 ], [
					pageParam,
					categoryParam,
					tagParam,
				] )
		);

	const slugs = ( param, ids ) =>
		( ids || [] )
			.map( ( id ) => termSlugs?.[ param ]?.[ id ] )
			.filter( Boolean );

	parts.push(
		...listParam( categoryParam, slugs( categoryParam, categories ) ),
		...listParam( tagParam, slugs( tagParam, tags ) )
	);
	if ( page > 1 ) {
		parts.push( `${ pageParam }=${ page }` );
	}

	const query = parts.length ? `?${ parts.join( '&' ) }` : '';
	return window.location.pathname + query;
};
