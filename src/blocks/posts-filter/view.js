/**
 * Filter state and actions in the shared `wmpgf` store. A change builds the
 * new URL and hands it to the router; the server does the filtering.
 */
import {
	store,
	getElement,
	getConfig,
	getServerState,
} from '@wordpress/interactivity';
import { urlWith } from '../../shared/url';
import { navigateTo } from '../../shared/navigate';
import { withSyncEvent } from '../../shared/sync-event';

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
		// Each checkbox's own value is its term's slug, so it needs no context.
		get isCategoryChecked() {
			return state.selectedCategories.includes(
				getElement().attributes.value
			);
		},
		get isTagChecked() {
			return state.selectedTags.includes( getElement().attributes.value );
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
		// On the group's fieldset: the event comes from the checkbox inside.
		*toggleCategory( event ) {
			state.selectedCategories = toggle(
				state.selectedCategories,
				event.target.value,
				event.target.checked
			);
			yield* applyFilters();
		},
		*toggleTag( event ) {
			state.selectedTags = toggle(
				state.selectedTags,
				event.target.value,
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
		submitSearch: withSyncEvent( function* ( event ) {
			event.preventDefault();
			const input = ++searchInput;
			yield* applyFilters();
			if ( input === searchInput ) {
				searchPending = false;
			}
		} ),
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
		clearFilters: withSyncEvent( function* ( event ) {
			event.preventDefault();
			++searchInput;
			state.selectedCategories = [];
			state.selectedTags = [];
			state.search = '';
			yield* applyFilters();
			searchPending = false;
		} ),
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
		// A pill selected in the URL can start out of view in its scrolling
		// row: each group's row on a phone, the one shared row on a wider
		// screen. Scroll that row, not the page, to the first selected pill.
		revealSelected() {
			const { ref } = getElement();
			const rows = new Set();

			for ( const input of ref.querySelectorAll( 'input:checked' ) ) {
				const pill = input.closest( '.wmpgf-filter__option' );
				const row = [
					pill.closest( '.wmpgf-filter__options' ),
					ref,
				].find( ( box ) => box.scrollWidth > box.clientWidth );
				if ( ! row || rows.has( row ) ) {
					continue;
				}
				rows.add( row );

				const rowBox = row.getBoundingClientRect();
				const pillBox = pill.getBoundingClientRect();
				const start =
					parseFloat(
						window.getComputedStyle( row ).scrollPaddingInlineStart
					) || 0;
				// The row's last 32px are under its fade.
				if (
					pillBox.left < rowBox.left + start ||
					pillBox.right > rowBox.right - 32
				) {
					row.scrollLeft += pillBox.left - rowBox.left - start;
				}
			}
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
