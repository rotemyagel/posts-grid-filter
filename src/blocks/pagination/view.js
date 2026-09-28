/**
 * Pagination state for the shared store. Prev/Next navigate normally (no
 * click handlers); these getters only keep their hrefs, label, and
 * visibility in step with filter changes made without a reload.
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
