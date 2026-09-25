/**
 * Adds filter state/actions to the shared `posts-grid-filter` store.
 * Toggling a checkbox updates selectedCategories/selectedTags (OR within
 * a taxonomy is expressed by simply collecting every checked term ID into
 * one array; the AND-across-taxonomies half of the brief's filtering logic
 * is handled entirely server-side by WordPress's own REST tax_query
 * behavior when posts-grid's `refresh()` sends both arrays).
 */
import { store, getContext } from '@wordpress/interactivity';

const { state, actions } = store( 'posts-grid-filter', {
	state: {
		get isCategoryChecked() {
			const { termId } = getContext();
			return state.selectedCategories.includes( termId );
		},
		get isTagChecked() {
			const { termId } = getContext();
			return state.selectedTags.includes( termId );
		},
	},
	actions: {
		toggleCategory( event ) {
			const { termId } = getContext();
			const checked = event.target.checked;
			state.selectedCategories = checked
				? [ ...state.selectedCategories, termId ]
				: state.selectedCategories.filter( ( id ) => id !== termId );
			state.page = 1;
			actions.refresh();
		},
		toggleTag( event ) {
			const { termId } = getContext();
			const checked = event.target.checked;
			state.selectedTags = checked
				? [ ...state.selectedTags, termId ]
				: state.selectedTags.filter( ( id ) => id !== termId );
			state.page = 1;
			actions.refresh();
		},
	},
} );
