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
| `Component` | `Function` | The React component to render. |
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
wp_enqueue_script( 'my-calendar', $url, array( 'gatherpress-query' ), $ver, true );
```

```js
const { EventOrderControls, EventCountControls } = window.gatherpress.queryControls;
```

Declaring `gatherpress-query` as a script dependency is what guarantees the global exists by the time your code runs. Available components: `EventCountControls`, `EventExcludeControls`, `EventIncludeUnfinishedControls`, `EventListTypeControls`, `EventOffsetControls`, `EventOrderControls` and `ShadowSourceFilterControls`.

### Resources

- [Unified Extensibility APIs in 6.6][devnote]

[devnote]: https://make.wordpress.org/core/2024/06/18/editor-unified-extensibility-apis-in-6-6/ "#devnote - Editor: Unified Extensibility APIs in 6.6 – Make WordPress Core"
