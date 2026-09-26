/**
 * Adds pagination state/actions to the shared `posts-grid-filter` store.
 *
 * The Prev/Next elements are real <a href="?pgf-page=N"> links (see
 * pagination/render.php), not just click targets: that's what makes page 2+
 * a URL a crawler (or a JS-disabled visitor) can actually reach, matching
 * the "foundation layer" pattern for SEO-friendly paginated/infinite-scroll
 * content (real, crawlable per-page URLs; JS is an enhancement on top, not
 * the only way in). This file is the enhancement layer: it intercepts the
 * click so navigating pages doesn't reload the whole document, but it still
 * updates the visible URL via history.pushState so the address bar, browser
 * back/forward, and "copy link" all stay correct.
 */
import { store } from '@wordpress/interactivity';

const urlForPage = ( pageNumber ) => {
	const url = new URL( window.location.href );
	url.searchParams.set( state.config?.pageParam || 'pgf-page', pageNumber );
	return url.toString();
};

const { state, actions } = store( 'posts-grid-filter', {
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
	actions: {
		goToPreviousPage( event ) {
			event.preventDefault();
			if ( state.page > 1 ) {
				state.page -= 1;
				history.pushState( null, '', urlForPage( state.page ) );
				actions.refresh();
			}
		},
		goToNextPage( event ) {
			event.preventDefault();
			if ( state.page < state.totalPages ) {
				state.page += 1;
				history.pushState( null, '', urlForPage( state.page ) );
				actions.refresh();
			}
		},
	},
} );
