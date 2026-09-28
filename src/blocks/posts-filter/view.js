/**
 * Filter state/actions for the shared store. The OR/AND logic is left to
 * the REST API's own tax_query handling of the two ID arrays.
 */
import { store, getContext } from '@wordpress/interactivity';
import { buildFilterUrl } from '../../shared/filter-url';

// replaceState, not pushState: Back shouldn't step through every toggle.
const syncUrl = () => {
	window.history.replaceState(
		window.history.state,
		'',
		buildFilterUrl( {
			page: 1,
			categories: state.selectedCategories,
			tags: state.selectedTags,
			termSlugs: state.termSlugs,
			config: state.config,
		} )
	);
};

// A new selection always starts from page one.
const applySelection = () => {
	state.page = 1;
	syncUrl();
	actions.refresh();
};

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
		get hideClearFilters() {
			return (
				state.selectedCategories.length === 0 &&
				state.selectedTags.length === 0
			);
		},
	},
	actions: {
		toggleCategory( event ) {
			const { termId } = getContext();
			state.selectedCategories = event.target.checked
				? [ ...state.selectedCategories, termId ]
				: state.selectedCategories.filter( ( id ) => id !== termId );
			applySelection();
		},
		toggleTag( event ) {
			const { termId } = getContext();
			state.selectedTags = event.target.checked
				? [ ...state.selectedTags, termId ]
				: state.selectedTags.filter( ( id ) => id !== termId );
			applySelection();
		},
		clearFilters( event ) {
			event.preventDefault();
			state.selectedCategories = [];
			state.selectedTags = [];
			applySelection();
		},
	},
} );
