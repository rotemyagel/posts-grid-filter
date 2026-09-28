/**
 * Fetch/render logic for the shared `wmpgf` store. The filter
 * and pagination blocks add their own state/actions to the same namespace,
 * which is how the blocks stay in sync without being nested.
 */
import { store } from '@wordpress/interactivity';
import { listParam } from '../../shared/filter-url';

/**
 * The same "medium" size the server render uses, with width/height so the
 * <img> reserves its space and filtering causes no layout shift.
 *
 * @param {Object} post REST API post resource.
 * @return {?{url: string, width?: number, height?: number}} Thumbnail info, or null.
 */
const getThumbnail = ( post ) => {
	const media = post._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ];
	if ( ! media ) {
		return null;
	}
	const medium = media.media_details?.sizes?.medium;
	if ( medium ) {
		return {
			url: medium.source_url,
			width: medium.width,
			height: medium.height,
		};
	}
	if ( media.source_url ) {
		return {
			url: media.source_url,
			width: media.media_details?.width,
			height: media.media_details?.height,
		};
	}
	return null;
};

/**
 * Decodes entities like `&#8211;` in REST titles. Only safe because the
 * input is already tag-free; this is not a sanitizer.
 *
 * @param {string} html Tag-free string that may contain HTML entities.
 * @return {string} Decoded plain text.
 */
const decodeEntities = ( html ) => {
	const el = document.createElement( 'textarea' );
	el.innerHTML = html;
	return el.textContent;
};

/**
 * Builds a card from DOM nodes and properties rather than an HTML string,
 * so titles and URLs are escaped by the browser. The excerpt is the one
 * exception: it's WordPress-rendered HTML, output as-is like the_excerpt().
 *
 * @param {Object} post REST API post resource.
 * @return {HTMLElement} A `.wmpgf-grid__card` <article> element.
 */
const renderPostCard = ( post ) => {
	const thumb = getThumbnail( post );
	const title = decodeEntities(
		( post.title?.rendered || '' ).replace( /<[^>]+>/g, '' )
	);
	const excerpt = post.excerpt?.rendered || '';

	const article = document.createElement( 'article' );
	article.className = 'wmpgf-grid__card';

	if ( thumb ) {
		const thumbLink = document.createElement( 'a' );
		thumbLink.href = post.link;
		thumbLink.className = 'wmpgf-grid__thumb';

		const img = document.createElement( 'img' );
		img.src = thumb.url;
		img.alt = title;
		if ( thumb.width && thumb.height ) {
			img.width = thumb.width;
			img.height = thumb.height;
		}
		img.loading = 'lazy';
		img.decoding = 'async';

		thumbLink.appendChild( img );
		article.appendChild( thumbLink );
	}

	const heading = document.createElement( 'h3' );
	heading.className = 'wmpgf-grid__title';
	const titleLink = document.createElement( 'a' );
	titleLink.href = post.link;
	titleLink.textContent = title;
	heading.appendChild( titleLink );
	article.appendChild( heading );

	const excerptEl = document.createElement( 'div' );
	excerptEl.className = 'wmpgf-grid__excerpt';
	excerptEl.innerHTML = excerpt;
	article.appendChild( excerptEl );

	return article;
};

// Only the latest request may render, so out-of-order responses from
// rapid clicks can't show results for an older selection.
let latestRequestId = 0;

const { state } = store( 'wmpgf', {
	// Server-seeded state (page, totalPages, selected*) is not declared here:
	// a client default could overwrite the hydrated server value.
	state: {
		isLoading: false,
		announcement: '',
	},
	actions: {
		/**
		 * Re-fetches posts for the current selection and replaces the list.
		 */
		async refresh() {
			const requestId = ++latestRequestId;
			state.isLoading = true;

			const restUrl = state.config?.restUrl;
			const list = document.querySelector( '[data-wmpgf-grid-list]' );
			if ( list ) {
				list.setAttribute( 'aria-busy', 'true' );
			}

			if ( ! restUrl ) {
				state.isLoading = false;
				return;
			}

			// Readable query: every value is a number or a fixed ASCII string,
			// and the REST API accepts comma-separated term IDs.
			const query = [
				`per_page=${ state.config?.postsPerPage || 6 }`,
				`page=${ state.page }`,
				...listParam( 'wmpgf_category', state.selectedCategories ),
				...listParam( 'wmpgf_tag', state.selectedTags ),
				'_embed=wp:featuredmedia',
				'_fields=id,link,title,excerpt,_links,_embedded',
			].join( '&' );
			// With plain permalinks restUrl already contains `?rest_route=`.
			const requestUrl =
				restUrl + ( restUrl.includes( '?' ) ? '&' : '?' ) + query;

			let result;
			try {
				const response = await fetch( requestUrl );
				result = {
					ok: response.ok,
					posts: response.ok ? await response.json() : null,
					total: parseInt(
						response.headers.get( 'X-WP-Total' ) || '0',
						10
					),
					totalPages: Math.max(
						1,
						parseInt(
							response.headers.get( 'X-WP-TotalPages' ) || '1',
							10
						)
					),
				};
			} catch ( error ) {
				result = { ok: false, posts: null, totalPages: null };
			}

			if ( requestId !== latestRequestId ) {
				return;
			}

			state.isLoading = false;
			if ( list ) {
				list.removeAttribute( 'aria-busy' );
			}

			if ( ! result.ok || ! result.posts ) {
				// Shown as an error, not as "No posts found".
				if ( list ) {
					list.replaceChildren();
					const notice = document.createElement( 'p' );
					notice.className = 'wmpgf-grid__error';
					notice.setAttribute( 'role', 'alert' );
					notice.textContent =
						state.config?.i18n?.loadError ||
						'Could not load posts. Please try again.';
					list.appendChild( notice );
				}
				return;
			}

			state.totalPages = result.totalPages;
			const i18n = state.config?.i18n || {};
			state.announcement = result.posts.length
				? ( i18n.results || 'Posts found: {count}' ).replace(
						'{count}',
						result.total
				  )
				: i18n.noResults || 'No posts found.';

			if ( list ) {
				if ( result.posts.length ) {
					list.replaceChildren(
						...result.posts.map( renderPostCard )
					);
				} else {
					const empty = document.createElement( 'p' );
					empty.className = 'wmpgf-grid__empty';
					empty.textContent =
						state.config?.i18n?.noResults || 'No posts found.';
					list.replaceChildren( empty );
				}
			}
		},
	},
} );
