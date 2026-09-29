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
		get isDark() {
			return state.colorScheme === 'dark';
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
		// Saved on this browser; the attribute on <html> restyles every
		// plugin block at once, and the inline script in render.php applies
		// it again on the next page load before the blocks paint.
		toggleColorScheme() {
			const { attribute, storageKey } = getConfig().colorScheme;
			const next = state.isDark ? 'light' : 'dark';
			document.documentElement.setAttribute( attribute, next );
			try {
				window.localStorage.setItem( storageKey, next );
			} catch {
				// Storage can be blocked; the choice then lasts for this page.
			}
			state.colorScheme = next;
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
		// The mode the blocks are showing: the saved choice, or else the
		// device setting, which the switch keeps following until clicked.
		initColorScheme() {
			const { attribute } = getConfig().colorScheme;
			const dark = window.matchMedia( '(prefers-color-scheme: dark)' );
			const current = () =>
				document.documentElement.getAttribute( attribute ) ||
				( dark.matches ? 'dark' : 'light' );

			state.colorScheme = current();
			const follow = () => ( state.colorScheme = current() );
			dark.addEventListener( 'change', follow );
			return () => dark.removeEventListener( 'change', follow );
		},
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
