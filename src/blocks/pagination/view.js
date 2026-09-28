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
import { buildFilterUrl } from '../../shared/filter-url';

const urlForPage = ( page ) =>
	buildFilterUrl( {
		page,
		categories: state.selectedCategories,
		tags: state.selectedTags,
		config: state.config,
	} );

const { state } = store( 'posts-grid-filter', {
	state: {
		get isFirstPage() {
			return state.page <= 1;
		},
		get isLastPage() {
			return state.page >= state.totalPages;
		},
		get isSinglePage() {
			return state.totalPages <= 1;
		},
		get paginationLabel() {
			const format =
				state.paginationLabelFormat || 'Page {current} of {total}';
			return format
				.replace( '{current}', state.page )
				.replace( '{total}', state.totalPages );
		},
		get prevHref() {
			return state.isFirstPage ? false : urlForPage( state.page - 1 );
		},
		get nextHref() {
			return state.isLastPage ? false : urlForPage( state.page + 1 );
		},
	},
} );
