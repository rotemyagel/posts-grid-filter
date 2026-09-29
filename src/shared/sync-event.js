/**
 * Marks an action that needs its event while it is dispatched, to call
 * preventDefault(). WordPress 6.8 added withSyncEvent() and hands every
 * other action the event after it; 6.7, the oldest version this plugin
 * supports, runs every action synchronously and doesn't have it, so there
 * this changes nothing.
 */
import * as interactivity from '@wordpress/interactivity';

export const withSyncEvent =
	interactivity.withSyncEvent || ( ( action ) => action );
