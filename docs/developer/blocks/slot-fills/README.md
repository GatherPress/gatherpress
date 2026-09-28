# Slots & fills in GatherPress Admin UI

Similar to the central entry points for blocks – GatherPress' [hookable-patterns](./../hookable-patterns/), the plugin provides central administrative entry-points within the post- and site-editor for all block settings.

GatherPress keeps relevant post data about the currently edited venue or event post within slot inside the `InseptorControls` panel, specific for each post type. These open slots are used by GatherPress itself and can be filled externally.

Every slot belongs to one of each post type. Additionally the venue-slot will be added to the event-slot automatically.

Every GatherPress block with own administrative sidebar-elements registers a fill for either the venue- or the events-slot. Plugin developers should provide their additions to GatherPress within the slots as well, which will help keeping the overall admin interface clean & consistent.

## Available Slots

- `EventPluginDocumentSettings` A slot that has all settings related to an event.
- `VenuePluginDocumentSettings` A slot that has all settings related to a venue.

All slots will be rendered into the `PluginDocumentSettingPanel` imported from the `@wordpress/editor` package. This panel is shown in the document sidebar for the event and venue post types [in both the post and site editor][devnote]. 

## Fills by GatherPress

- `VenuePluginFill` loads the `VenuePluginDocumentSettings` slot into the `EventPluginDocumentSettings` slot, so that venue changes can be made from within an event context.


## Add UI elements

```js
export default function GatherPressAwesomeFill() {
	return (
		<>
			<Fill name="EventPluginDocumentSettings">
				<p>A note that will be seen in the document sidebar under "Event settings".</p>
			</Fill>
		</>
	);
}
```


## Event Query Loop controls

The Event Query Loop keeps its own slot, separate from the document sidebar ones above. GatherPress fills it with the controls in the **Event Query Settings** panel, and two filters let you change that list rather than only add to it.

### `gatherpress.eventQueryControls`

Filters the controls shown when the query is not inherited. Each entry is an object:

| Key | Type | Description |
|---|---|---|
| `name` | `string` | Stable identifier. Use a plugin namespace for your own, e.g. `my-calendar/density`. |
| `Component` | `Function` or `Object` | The React component to render: a function or class, or a `memo`, `forwardRef` or `lazy` wrapper. Anything else is skipped rather than rendered. |
| `props` | `Object` | Optional. Merged over the block edit props the slot passes down. |

The controls GatherPress registers, in render order:

| `name` | Control |
|---|---|
| `listType` | Upcoming / Past |
| `includeUnfinished` | Include events already under way |
| `exclude` | Exclude the current event. Only present when the query and its host are the same post type |
| `shadowSourceFilter` | Filter by the current venue or other shadow source. Only present in a template, or on a shadow-source host querying a different post type |
| `count` | Events per page |
| `offset` | Offset |
| `order` | Order and order by |

The two conditional entries are absent rather than present-and-hidden, so the array is always exactly what would render.

Removing `shadowSourceFilter` removes the toggle only. A block that already has the filter switched on keeps its source in step with the post being edited, because that sync runs separately from the control.

A calendar has no use for Upcoming / Past or an offset, so it can drop them:

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
	'gatherpress.eventQueryControls',
	'my-calendar/trim-controls',
	( controls ) =>
		controls.filter(
			( { name } ) => ! [ 'listType', 'offset' ].includes( name )
		)
);
```

Reordering, wrapping and inserting all work the same way, since you are handed the array and return one.

### `gatherpress.eventInheritedQueryControls`

The same shape, for the shorter set shown when the query inherits from the template: `listType`, `includeUnfinished` and `order`. It is a separate filter because a rule that suits one panel rarely suits the other.

### Reusing the controls

Removing a control is one thing; building a panel of your own out of GatherPress's controls is another. The components are published on a global for that:

```php
wp_enqueue_script( 'my-calendar', $url, array( 'gatherpress-query-controls' ), $ver, true );
```

```js
const { EventOrderControls, EventCountControls } = window.gatherpress.queryControls;
```

Declaring `gatherpress-query-controls` as a script dependency is what guarantees the global exists by the time your code runs. `ShadowSourceFilterControls` keeps the query's source current on its own when rendered in your panel, so there is nothing extra to wire up for it. Available components: `EventCountControls`, `EventExcludeControls`, `EventIncludeUnfinishedControls`, `EventListTypeControls`, `EventOffsetControls`, `EventOrderControls` and `ShadowSourceFilterControls`.

#### Importing instead of reading the global

If you build with `@wordpress/scripts`, you can write an import and let the build turn it into the global, which is exactly how every `@wordpress/*` import already works. Add two options to the dependency extraction plugin in your `webpack.config.js`:

```js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const GATHERPRESS = '@gatherpress/';
const surface = ( request ) => request.slice( GATHERPRESS.length );
const camelCase = ( name ) => name.replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );

// Swap wp-scripts' extraction plugin, which it creates with no options, for
// one that also knows about GatherPress. Anything else falls through to the
// usual `@wordpress/*` handling.
const withGatherPress = ( config ) => ( {
	...config,
	plugins: [
		...config.plugins.filter(
			( plugin ) => 'DependencyExtractionWebpackPlugin' !== plugin.constructor.name
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal: ( request ) =>
				request.startsWith( GATHERPRESS )
					? [ 'gatherpress', camelCase( surface( request ) ) ]
					: undefined,
			requestToHandle: ( request ) =>
				request.startsWith( GATHERPRESS )
					? `gatherpress-${ surface( request ) }`
					: undefined,
		} ),
	],
} );

// With --experimental-modules, wp-scripts exports [ scripts, modules ]. Only
// the scripts config handles these imports.
module.exports = Array.isArray( defaultConfig )
	? [ withGatherPress( defaultConfig[ 0 ] ), ...defaultConfig.slice( 1 ) ]
	: withGatherPress( defaultConfig );
```

Then:

```js
import { EventOrderControls } from '@gatherpress/query-controls';
```

compiles to a read of `window.gatherpress.queryControls`, and `gatherpress-query-controls` lands in your built `index.asset.php` without you listing it. Nothing is fetched from npm; the name only has to follow the [public JavaScript surface convention](../../hooks-naming-convention.md#public-javascript-surfaces), and one rule covers every surface GatherPress publishes.

### Resources

- [Unified Extensibility APIs in 6.6][devnote]

[devnote]: https://make.wordpress.org/core/2024/06/18/editor-unified-extensibility-apis-in-6-6/ "#devnote - Editor: Unified Extensibility APIs in 6.6 – Make WordPress Core"
