/**
 * The grid's markup comes from render.php, and filter and page changes
 * replace it through the router. This store owns only its loading states:
 * the dim and the skeleton while a new page is on its way, and each card
 * image's placeholder until the image has loaded.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'wmpgf', {
	state: {
		isLoading: false,
		showSkeleton: false,
	},
	actions: {
		// Fires on load and on error, so a broken image never shimmers forever.
		imageLoaded() {
			getContext().imageLoading = false;
		},
	},
	callbacks: {
		// An image that finished before this script ran never fires load,
		// so only one still loading gets the placeholder.
		watchImage() {
			getContext().imageLoading = ! getElement().ref.complete;
		},
	},
} );
