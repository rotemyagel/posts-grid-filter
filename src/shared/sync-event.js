/**
 * Marks an action that needs its event while it is dispatched, to call
 * preventDefault(). WordPress 6.8 added withSyncEvent() and hands every
 * other action the event after it; 6.7, the oldest version this plugin
 * supports, runs every action synchronously and doesn't have it, so there
 * this changes nothing.
 */
import * as interactivity from '@wordpress/interactivity';

// Looked up at run time. Written as interactivity.withSyncEvent, the
// build turns it into a named import, and on 6.7, which doesn't export
// it, that import stops the whole module from loading.
const available = Reflect.get( interactivity, 'withSyncEvent' );

export const withSyncEvent = available || ( ( action ) => action );
