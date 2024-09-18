# Slots & fills in GatherPress Admin UI

Similar to the hookable patterns, GatherPress provides multiple ways to modify its admin user-interface using slots and fills.

## Available Slots

- `EventPluginDocumentSettings` A slot that has all settings related to an event.
- `VenuePluginDocumentSettings` A slot that has all settings related to a venue.

All slots will be rendered into the `PluginDocumentSettingPanel` imported from the `@wordpress/editor` package. This panel is shown in the document sidebar for the event and venue post types [in both the post and site editor][devnote]. 

## Fills by GatherPress

- `VenuePluginFill` loads the `VenuePluginDocumentSettings` slot into the `EventPluginDocumentSettings` slot, so that venue changes can be made from within an event context.


## Add or remove UI elemnts

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


### Ressources

- [Unified Extensibility APIs in 6.6][devnote]

[devnote]: https://make.wordpress.org/core/2024/06/18/editor-unified-extensibility-apis-in-6-6/ "#devnote - Editor: Unified Extensibility APIs in 6.6 – Make WordPress Core"