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


## Add or remove UI elements

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


### Resources

- [Unified Extensibility APIs in 6.6][devnote]

[devnote]: https://make.wordpress.org/core/2024/06/18/editor-unified-extensibility-apis-in-6-6/ "#devnote - Editor: Unified Extensibility APIs in 6.6 – Make WordPress Core"
