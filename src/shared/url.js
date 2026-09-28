/**
 * Builds readable URLs for the current page, e.g.
 * `?wmpgf-category=design,culture&wmpgf-page=2`. Mirrors
 * WMPGF_Request::url() in PHP. Written by hand rather than through
 * URLSearchParams, which would encode every `,` as %2C.
 */

/**
 * Whether a raw query key is `name`, `name[]`, `name[0]`, or one of those
 * with the brackets percent-encoded (as a GET form submits them).
 *
 * @param {string} rawKey Key as it appears in the query string.
 * @param {string} name   Param name.
 * @return {boolean} True if the key belongs to `name`.
 */
const isParam = ( rawKey, name ) => {
	const key = rawKey.replace( /%5B/gi, '[' ).replace( /%5D/gi, ']' );
	return key === name || key.startsWith( `${ name }[` );
};

/**
 * Current URL with some params replaced. Params not named in `changes`
 * keep their current value; the page resets to 1 unless given.
 *
 * @param {Object}   changes              Params to set.
 * @param {string[]} [changes.categories] Category slugs.
 * @param {string[]} [changes.tags]       Tag slugs.
 * @param {string}   [changes.search]     Search term.
 * @param {number}   [changes.perPage]    Posts per page.
 * @param {number}   [changes.page]       Page number; 1 omits the param.
 * @param {Object}   params               Param names, from getConfig().params.
 * @return {string} Path and query.
 */
export const urlWith = ( changes, params ) => {
	const replaced = [
		params.page,
		...Object.keys( changes ).map( ( key ) => params[ key ] ),
	];

	const parts = window.location.search
		.slice( 1 )
		.split( '&' )
		.filter(
			( part ) =>
				part &&
				! replaced.some( ( name ) =>
					isParam( part.split( '=' )[ 0 ], name )
				)
		);

	[ 'categories', 'tags' ].forEach( ( key ) => {
		if ( changes[ key ]?.length ) {
			parts.push( `${ params[ key ] }=${ changes[ key ].join( ',' ) }` );
		}
	} );
	// Free text, so it is the one value that must be encoded.
	if ( changes.search ) {
		parts.push(
			`${ params.search }=${ encodeURIComponent( changes.search ) }`
		);
	}
	if ( changes.perPage ) {
		parts.push( `${ params.perPage }=${ Number( changes.perPage ) }` );
	}
	if ( changes.page > 1 ) {
		parts.push( `${ params.page }=${ changes.page }` );
	}

	return (
		window.location.pathname +
		( parts.length ? `?${ parts.join( '&' ) }` : '' )
	);
};
