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

### Swapping the auto-loaded template — `gatherpress.rsvpResponseDefaultTemplate`

To change what auto-loaded instances seed (without going through the picker
flow), filter the default template directly. The filter receives an
`InnerBlocks` tuple tree and must return one of the same shape:

```js
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

addFilter(
    'gatherpress.rsvpResponseDefaultTemplate',
    'my-plugin/swap-rsvp-default',
    () => [
        [
            'core/group',
            { layout: { type: 'grid', columns: 3 } },
            [ [ 'gatherpress/rsvp-template', {} ] ],
        ],
    ]
);
```

This only affects the auto-loaded path. Manual inserts still go through the
picker, which is filtered separately via `gatherpress.rsvpResponsePatterns`.

## RSVP Form — `gatherpress.rsvpFormPatterns`

Same shape as RSVP Response. Filters the array of starter patterns shown in
the RSVP Form block's picker. Each entry is `{ name, title, description, template }`.

The bundled default (_Standard RSVP Form_) is the only entry registered out
of the box. Add your own:

```js
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

addFilter(
    'gatherpress.rsvpFormPatterns',
    'my-plugin/extra-rsvp-form',
    ( patterns ) => [
        ...patterns,
        {
            name: 'my-plugin/minimal',
            title: __( 'Minimal', 'my-plugin' ),
            description: __(
                'Name + email + submit only.',
                'my-plugin'
            ),
            template: [
                /* ...InnerBlocks tuples... */
            ],
        },
    ]
);
```

### Swapping the RSVP Form auto-loaded template — `gatherpress.rsvpFormDefaultTemplate`

Parallel to `gatherpress.rsvpResponseDefaultTemplate` — filters the template
seeded into auto-loaded RSVP Form blocks (currently a no-op since the block
isn't auto-included by the event post type's `template` arg, but kept for
parity in case future patterns add it).

## Adding a picker to a new block

The picker component lives at [`src/components/PatternPicker/`](../../../src/components/PatternPicker/)
and is exported as the default plus a named `PatternChooserModal` export for
toolbar-driven re-opens. See the RSVP Response wiring at
[`src/blocks/rsvp-response/edit.js`](../../../src/blocks/rsvp-response/edit.js)
for the canonical integration: a `patternPicked` boolean attribute on the
block, a `useSelect` for inner-block count, the picker render gated on
`! patternPicked && 0 === innerBlockCount`, and a `BlockControls` toolbar
entry that re-opens `<PatternChooserModal>` for already-populated blocks.
