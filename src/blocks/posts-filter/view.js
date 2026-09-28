/**
 * Adds filter state/actions to the shared `posts-grid-filter` store.
 * Toggling a checkbox updates selectedCategories/selectedTags (OR within
 * a taxonomy is expressed by simply collecting every checked term ID into
 * one array; the AND-across-taxonomies half of the brief's filtering logic
 * is handled entirely server-side by WordPress's own REST tax_query
 * behavior when posts-grid's `refresh()` sends both arrays). clearFilters()
 * resets both arrays at once, for the "Clear filters" link in render.php.
 */
import { store, getContext } from '@wordpress/interactivity';
import { buildFilterUrl } from '../../shared/filter-url';

/**
 * Mirrors the selection into the address bar, so a refresh or a copied
 * link reopens the same filtered view (render.php reads these params on
 * the server). replaceState rather than pushState: one history entry per
 * checkbox click would make Back step through every individual toggle.
 */
const syncUrl = () => {
	window.history.replaceState(
		window.history.state,
		'',
		buildFilterUrl( {
			page: 1,
			categories: state.selectedCategories,
			tags: state.selectedTags,
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
