/**
 * Builds a URL for the current page carrying a given page number and
 * category/tag selection, in the same `?pgf-page=N&pgf_category[]=ID`
 * shape the server-side render.php files read. Shared by pagination's
 * Prev/Next hrefs and the filter's address-bar sync, so both always
 * produce identical URLs for the same selection.
 */

/**
 * Removes every representation of a bracketed array param: the plain
 * `name[]` form this plugin writes, and the indexed `name[0]`, `name[1]`
 * form that add_query_arg()/remove_query_arg() produce when they round-trip
 * an existing value through PHP's array parsing. Leaving the indexed form
 * in place while appending fresh `name[]=` values makes PHP merge both into
 * one array, so a changed selection would combine with the old one instead
 * of replacing it. The startsWith() check stops an unrelated param of the
 * same length (e.g. `foo_bar[0]` vs `pgf_tag`) from being deleted.
 *
 * @param {URLSearchParams} searchParams Mutated in place.
 * @param {string}          name         Base param name, e.g. "pgf_category".
 */
const clearArrayParam = ( searchParams, name ) => {
	const toDelete = [];
	for ( const key of searchParams.keys() ) {
		if (
			key === `${ name }[]` ||
			( key.startsWith( name ) &&
				/^\[\d+\]$/.test( key.slice( name.length ) ) )
		) {
			toDelete.push( key );
		}
	}
	toDelete.forEach( ( key ) => searchParams.delete( key ) );
};

/**
 * @param {Object}   args
 * @param {number}   args.page       Page number; 1 omits the page param.
 * @param {number[]} args.categories Selected pgf_category term IDs.
 * @param {number[]} args.tags       Selected pgf_tag term IDs.
 * @param {Object}   [args.config]   state.config (param names), if present.
 * @return {string} Absolute URL.
 */
export const buildFilterUrl = ( { page, categories, tags, config } ) => {
	const url = new URL( window.location.href );
	const pageParam = config?.pageParam || 'pgf-page';
	const categoryParam = config?.categoryParam || 'pgf_category';
	const tagParam = config?.tagParam || 'pgf_tag';

	if ( page > 1 ) {
		url.searchParams.set( pageParam, page );
	} else {
		url.searchParams.delete( pageParam );
	}

	clearArrayParam( url.searchParams, categoryParam );
	( categories || [] ).forEach( ( id ) =>
		url.searchParams.append( `${ categoryParam }[]`, id )
	);

	clearArrayParam( url.searchParams, tagParam );
	( tags || [] ).forEach( ( id ) =>
		url.searchParams.append( `${ tagParam }[]`, id )
	);

	return url.toString();
};
