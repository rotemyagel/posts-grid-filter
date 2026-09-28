/**
 * Filter state and actions in the shared `wmpgf` store. A change builds the
 * new URL and hands it to the router; the server does the filtering.
 */
import {
	store,
	getContext,
	getConfig,
	getServerState,
} from '@wordpress/interactivity';
import { urlWith } from '../../shared/url';
import { navigateTo } from '../../shared/navigate';

const toggle = ( list, value, on ) =>
	on ? [ ...list, value ] : list.filter( ( item ) => item !== value );

// Bumped on every keystroke, so only the last one after the pause searches,
// and so the server's copy of the term doesn't overwrite what's being typed.
let searchInput = 0;
let searchPending = false;

function* applyFilters() {
	yield* navigateTo(
		state,
		urlWith(
			{
				categories: state.selectedCategories,
				tags: state.selectedTags,
				search: state.search.trim(),
			},
			getConfig().params
		)
	);
}

const { state } = store( 'wmpgf', {
	state: {
		get isCategoryChecked() {
			return state.selectedCategories.includes( getContext().slug );
		},
		get isTagChecked() {
			return state.selectedTags.includes( getContext().slug );
		},
		get hideClearFilters() {
			return (
				! state.selectedCategories.length &&
				! state.selectedTags.length &&
				! state.search.trim()
			);
		},
	},
	actions: {
		*toggleCategory( event ) {
			state.selectedCategories = toggle(
				state.selectedCategories,
				getContext().slug,
				event.target.checked
			);
			yield* applyFilters();
		},
		*toggleTag( event ) {
			state.selectedTags = toggle(
				state.selectedTags,
				getContext().slug,
				event.target.checked
			);
			yield* applyFilters();
		},
		*updateSearch( event ) {
			state.search = event.target.value;
			searchPending = true;
			const input = ++searchInput;
			yield new Promise( ( resolve ) =>
				setTimeout( resolve, getConfig().searchDelay )
			);
			if ( input !== searchInput ) {
				return;
			}
			yield* applyFilters();
			if ( input === searchInput ) {
				searchPending = false;
			}
		},
		// Enter in the search field: search now instead of after the pause.
		*submitSearch( event ) {
			event.preventDefault();
			const input = ++searchInput;
			yield* applyFilters();
			if ( input === searchInput ) {
				searchPending = false;
			}
		},
		*clearFilters( event ) {
			event.preventDefault();
			++searchInput;
			state.selectedCategories = [];
			state.selectedTags = [];
			state.search = '';
			yield* applyFilters();
			searchPending = false;
		},
	},
	callbacks: {
		// After every navigation (including Back and Forward) the router
		// updates the server state; copy the selection and count from it.
		syncFromServer() {
			const server = getServerState();
			state.selectedCategories = server.selectedCategories;
			state.selectedTags = server.selectedTags;
			state.resultsLabel = server.resultsLabel;
			if ( ! searchPending ) {
				state.search = server.search;
			}
		},
	},
} );
