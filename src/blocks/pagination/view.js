/**
 * Prev/Next are real links (see render.php). With JavaScript, a plain
 * click loads the page through the router instead of a full reload; the
 * link's href stays the source of truth.
 */
import { store, getElement, getConfig } from '@wordpress/interactivity';
import { urlWith } from '../../shared/url';
import { navigateTo } from '../../shared/navigate';

const { state } = store( 'wmpgf', {
	actions: {
		// Keeps the current filters and search, starts again from page 1.
		*changePerPage( event ) {
			yield* navigateTo(
				state,
				urlWith(
					{ perPage: Number( event.target.value ) },
					getConfig().params
				)
			);
		},
		*goToPage( event ) {
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

			// Bring the new page's first posts into view if we scrolled past them.
			if ( region && region.getBoundingClientRect().top < 0 ) {
				region.scrollIntoView( { block: 'start' } );
			}
		},
	},
} );
