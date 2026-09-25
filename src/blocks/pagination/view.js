/**
 * Adds pagination state/actions to the shared `posts-grid-filter` store.
 * `goToPreviousPage`/`goToNextPage` only touch `state.page`, then delegate
 * the actual fetch to `actions.refresh`, which the posts-grid block's
 * view.js defines on the same merged store.
 */
import { store } from '@wordpress/interactivity';

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
	},
	actions: {
		goToPreviousPage() {
			if ( state.page > 1 ) {
				state.page -= 1;
				actions.refresh();
			}
		},
		goToNextPage() {
			if ( state.page < state.totalPages ) {
				state.page += 1;
				actions.refresh();
			}
		},
	},
} );
