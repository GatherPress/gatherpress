# Pattern pickers

Several GatherPress blocks open a pattern picker on insert — a placeholder UI
with a **Choose** button that surfaces a modal of named starter layouts. The
picker is powered by the reusable [`PatternPicker`](../../../src/components/PatternPicker/)
component and each consuming block exposes a JS filter so third parties can
register their own patterns without forking the block.

## RSVP Response — `gatherpress.rsvpResponsePatterns`

Filters the array of starter patterns shown in the RSVP Response block's
picker. Each entry is an object:

| Key | Type | Description |
|---|---|---|
| `name` | `string` | Stable identifier. Use a plugin namespace, e.g. `my-plugin/compact`. |
| `title` | `string` | Translated, human-readable label shown beneath the preview thumbnail. |
| `description` | `string` | Translated sentence-length summary. Surfaced for screen readers. |
| `template` | `Array` | `InnerBlocks` tuple tree — `[ blockName, attributes, innerBlocks ]` — used to seed the block when the pattern is picked. |

The bundled default (_Attendee Grid with Filter_) is the only entry registered
out of the box. Add your own:

```js
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

addFilter(
    'gatherpress.rsvpResponsePatterns',
    'my-plugin/extra-rsvp-pattern',
    ( patterns ) => [
        ...patterns,
        {
            name: 'my-plugin/compact',
            title: __( 'Compact', 'my-plugin' ),
            description: __(
                'Avatar grid only — no status filter.',
                'my-plugin'
            ),
            template: [
                [
                    'core/group',
                    { layout: { type: 'grid', columns: 3 } },
                    [ [ 'gatherpress/rsvp-template', {} ] ],
                ],
            ],
        },
    ]
);
```

### Auto-loaded instances

The RSVP Response block instance auto-loaded into a new event post (via the
`gatherpress_event` post type's `template` arg) carries
`patternPicked: true` so the picker is suppressed and the block seeds the
default template directly. Authors can still swap layouts via the block
toolbar's **Choose pattern** action — that opens the same modal your filter
contributes to.

## Adding a picker to a new block

The picker component lives at [`src/components/PatternPicker/`](../../../src/components/PatternPicker/)
and is exported as the default plus a named `PatternChooserModal` export for
toolbar-driven re-opens. See the RSVP Response wiring at
[`src/blocks/rsvp-response/edit.js`](../../../src/blocks/rsvp-response/edit.js)
for the canonical integration: a `patternPicked` boolean attribute on the
block, a `useSelect` for inner-block count, the picker render gated on
`! patternPicked && 0 === innerBlockCount`, and a `BlockControls` toolbar
entry that re-opens `<PatternChooserModal>` for already-populated blocks.
