/**
 * Loads a URL through the Interactivity Router: the server renders the
 * page, and only the grid's router region and the store's server state are
 * swapped in. The router module is imported on first use, so pages where
 * nobody filters never download it.
 */

// Counts overlapping navigations, so the loading state doesn't switch off
// while a newer one is still running. The router itself drops stale results.
let pending = 0;
let skeletonTimer;

// Loads quicker than this only dim the grid; a skeleton would just flash.
const SKELETON_DELAY = 200;

/**
 * @param {Object} state Store state (isLoading, showSkeleton).
 * @param {string} url   URL to load.
 */
export function* navigateTo( state, url ) {
	pending++;
	state.isLoading = true;
	clearTimeout( skeletonTimer );
	skeletonTimer = setTimeout( () => {
		state.showSkeleton = pending > 0;
	}, SKELETON_DELAY );

	try {
		const { actions } = yield import( '@wordpress/interactivity-router' );
		yield actions.navigate( url );
	} finally {
		pending--;
		state.isLoading = pending > 0;
		if ( ! pending ) {
			clearTimeout( skeletonTimer );
			state.showSkeleton = false;
		}
	}
}
