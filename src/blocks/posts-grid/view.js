/**
 * The grid itself has no actions: its markup comes from render.php, and
 * filter and page changes replace it through the router. It only owns the
 * loading flag that dims it while a new page is on its way.
 */
import { store } from '@wordpress/interactivity';

store( 'wmpgf', {
	state: {
		isLoading: false,
	},
} );
