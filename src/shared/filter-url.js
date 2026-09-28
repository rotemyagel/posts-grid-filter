/**
 * Builds page URLs with the current selection, for pagination hrefs and
 * the filter's address-bar sync.
 */

/**
 * Removes both `name[]` and the `name[0]` form that add_query_arg()
 * produces; PHP would merge the two into one array.
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
