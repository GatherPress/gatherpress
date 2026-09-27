/**
 * Internal dependencies
 */
import {
	EventCountControls,
	EventExcludeControls,
	EventIncludeUnfinishedControls,
	EventListTypeControls,
	EventOffsetControls,
	EventOrderControls,
	ShadowSourceFilterControls,
} from './components';

/**
 * Publishes the event query controls for companion plugins.
 *
 * `gatherpress.eventQueryControls` lets a plugin take controls away. Building
 * a panel of its own needs the components themselves, and they are otherwise
 * sealed inside this bundle, so they go on a global the way core publishes
 * `wp.*`. The plugin that reads them declares `gatherpress-query` as a script
 * dependency, which is what guarantees this has run first.
 *
 * @since TBD
 *
 * @example
 *   wp_enqueue_script( 'my-calendar', $url, array( 'gatherpress-query' ), $ver, true );
 *
 *   const { EventOrderControls } = window.gatherpress.queryControls;
 */
window.gatherpress ??= {};
window.gatherpress.queryControls = {
	EventCountControls,
	EventExcludeControls,
	EventIncludeUnfinishedControls,
	EventListTypeControls,
	EventOffsetControls,
	EventOrderControls,
	ShadowSourceFilterControls,
};
