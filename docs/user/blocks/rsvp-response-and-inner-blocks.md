# RSVP Response and Inner Blocks

The RSVP Response block and its inner block display Attendees on an event.

## Picking a starter pattern

When you insert the RSVP Response block manually (outside an event post), the
block opens a placeholder UI with a **Choose** button. Clicking it surfaces a
modal listing every registered RSVP Response pattern; clicking a pattern
seeds the block's inner content with that layout. The block toolbar's
**Choose pattern** action is also available afterwards if you want to swap the
layout.

On a new event post, the canonical _Attendee Grid with Filter_ pattern is
auto-loaded — picking a layout would be a redundant click for the
auto-included instance, so the picker is suppressed there. The toolbar's
**Choose pattern** still works if you want a different layout.

Developers can add their own patterns to the picker via the
`gatherpress.rsvpResponsePatterns` JS filter — see [the developer guide](../../developer/blocks/README.md).

## Editing the inner content

If you want to edit the content of the RSVP Response Block, you first need to toggle off the Block Guard.

You will then see the default inner content that you can modify. Be careful!

- Row
    - Row
        - RSVP Response Toggle
- Grid
    - RSVP Template (group)
        - Avatar (If listed as Anonymous, the default "Mystery person" will be displayed instead)
        - Display Name (If listed as Anonymous, the name will be be replaced by Anonymous)
        - RSVP Guest Count Display (Note: if the event is set not to accept guests, this field is greyed out in the editor and will not display on front end).
- Empty RSVP
