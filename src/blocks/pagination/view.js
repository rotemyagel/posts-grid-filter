/**
 * Prev/Next are real links (see render.php). With JavaScript, a plain
 * click loads the page through the router instead of a full reload; the
 * link's href stays the source of truth.
 */
import { store, getElement, getConfig } from '@wordpress/interactivity';
import { urlWith } from '../../shared/url';
import { navigateTo } from '../../shared/navigate';
import { withSyncEvent } from '../../shared/sync-event';

const { state } = store( 'wmpgf', {
	actions: {
		// Keeps the current filters and search, starts again from page 1.
		*changePerPage( event ) {
			const region = getElement().ref.closest(
				'[data-wp-router-region]'
			);
			yield* navigateTo(
				state,
				urlWith(
					{ perPage: Number( event.target.value ) },
					getConfig().params
				)
			);

			// The re-render drops focus to the page body; put it back on the select.
			region
				?.querySelector( '.wmpgf-pagination__per-page select' )
				?.focus();
		},
		goToPage: withSyncEvent( function* ( event ) {
			// Let the browser handle new-tab and new-window clicks.
			if (
				event.button !== 0 ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey
			) {
				return;
			}
			event.preventDefault();

			const { ref } = getElement();
			const region = ref.closest( '[data-wp-router-region]' );
			yield* navigateTo( state, ref.href );
			if ( ! region ) {
				return;
			}

			// The re-render drops focus to the page body. Move it to the new
			// page's first post, where a full page load would leave a reader.
			region
				.querySelector( '.wmpgf-grid__title a' )
				?.focus( { preventScroll: true } );

			// Bring the new page's first posts into view if we scrolled past them.
			if ( region.getBoundingClientRect().top < 0 ) {
				region.scrollIntoView( { block: 'start' } );
			}
		} ),
	},
} );
