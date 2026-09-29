/**
 * The grid's markup comes from render.php, and filter and page changes
 * replace it through the router. This store owns only the loading state of
 * those changes: the dim, and the skeleton when one is slow. Card images
 * need no script: each thumbnail's background is its placeholder, and the
 * image covers it once it paints.
 */
import { store } from '@wordpress/interactivity';

store( 'wmpgf', {
	state: {
		isLoading: false,
		showSkeleton: false,
	},
} );
