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
 * `wp.*`.
 *
 * This is the `query-controls` surface: `@gatherpress/query-controls` in an
 * import, `window.gatherpress.queryControls` at runtime, and the
 * `gatherpress-query-controls` handle to depend on. Declaring that handle is
 * what guarantees this has run first.
 *
 * @since TBD
 *
 * @example
 *   wp_enqueue_script( 'my-calendar', $url, array( 'gatherpress-query-controls' ), $ver, true );
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
