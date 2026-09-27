/**
 * Adds pagination state to the shared `posts-grid-filter` store. No click
 * actions here on purpose: Prev/Next (see pagination/render.php) are plain
 * <a href="?pgf-page=N"> links that navigate normally, with a full page
 * reload -- that is the whole point. A crawler, a JS-disabled visitor, or
 * someone who just bookmarks or shares the link all reach a real, correctly
 * server-rendered page this way, which is what "SEO-friendly pagination"
 * (as opposed to infinite-scroll/"load more" content that only exists after
 * a client-side fetch) actually means.
 *
 * The only job left for JavaScript here is keeping the *href* itself
 * accurate as the Posts Filter block's own AJAX interaction changes which
 * categories/tags are selected (posts-grid/view.js's `refresh()` handles
 * that fetch; this file never calls it). Without this, clicking Next while
 * a filter was chosen through the AJAX filter UI would silently drop that
 * filter on the full-page reload, since the link's href was rendered at
 * initial page load, before the filter selection existed.
 */
import { store } from '@wordpress/interactivity';

const urlForPage = ( pageNumber ) => {
	const url = new URL( window.location.href );
	const pageParam = state.config?.pageParam || 'pgf-page';
	const categoryParam = state.config?.categoryParam || 'pgf_category';
	const tagParam = state.config?.tagParam || 'pgf_tag';

	url.searchParams.set( pageParam, pageNumber );

	url.searchParams.delete( `${ categoryParam }[]` );
	( state.selectedCategories || [] ).forEach( ( id ) =>
		url.searchParams.append( `${ categoryParam }[]`, id )
	);

	url.searchParams.delete( `${ tagParam }[]` );
	( state.selectedTags || [] ).forEach( ( id ) =>
		url.searchParams.append( `${ tagParam }[]`, id )
	);

	return url.toString();
};

const { state } = store( 'posts-grid-filter', {
	state: {
		get isFirstPage() {
			return state.page <= 1;
		},
		get isLastPage() {
			return state.page >= state.totalPages;
		},
		get paginationLabel() {
			return `Page ${ state.page } of ${ state.totalPages }`;
		},
		get prevHref() {
			return state.isFirstPage ? false : urlForPage( state.page - 1 );
		},
		get nextHref() {
			return state.isLastPage ? false : urlForPage( state.page + 1 );
		},
	},
} );
