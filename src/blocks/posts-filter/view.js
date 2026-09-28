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

function* applyFilters() {
	yield* navigateTo(
		state,
		urlWith(
			{ categories: state.selectedCategories, tags: state.selectedTags },
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
				! state.selectedCategories.length && ! state.selectedTags.length
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
		*clearFilters( event ) {
			event.preventDefault();
			state.selectedCategories = [];
			state.selectedTags = [];
			yield* applyFilters();
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
		},
	},
} );
