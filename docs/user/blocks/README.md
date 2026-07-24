# Blocks

[Event Query](./event-query.md)

Event List (deprecated in 0.34 — replaced by the [Event Query](./event-query.md) block; existing blocks are migrated by GatherPress Alpha)

[RSVP and its inner blocks](./rsvp-and-inner-blocks.md) (RSVP Form and fields, Modal Manager, etc)

[RSVP Response and its inner blocks](./rsvp-response-and-inner-blocks.md) (RSVP Response Toggle, Avatar Display Name, RSVP Guest Count Display, etc)

Add to Calendar: Allows a user to add an event to their preferred calendar application. This saves a one-time copy of the event; for live, auto-updating subscriptions by venue, topic, or site, see [Calendar feeds](../calendar-feeds.md).

## Other blocks used in an event

See details on [Creating and managing events](../creating-and-managing-events.md)

Event Date
Venue
Online Event

## Block Guard

Some GatherPress blocks use the Block Guard mechanism, which protects them from accidental edits to their inner blocks.

Complex blocks like RSVP are built from many inner blocks (buttons, modals, text). Without protection, a stray click inside one of them selects an inner piece, and dragging from there pulls that piece out of place. Block Guard prevents this: a guarded block behaves as a single unit until you deliberately step into it.

There is no setting to turn on or off. The block simply responds to how you interact with it:

- **Click it once** to select the whole block. It stays protected, so dragging now moves the entire block — you cannot grab a piece out of it by accident.
- **Double-click** to work inside it. If you double-click on text, your cursor lands right where you clicked so you can start typing. Double-clicking elsewhere — a button, an image, empty space — still opens the block, but you then click the part you want.
- **Click outside the block** and the protection comes back on. Moving to a different spot inside an open block does not re-protect it; only leaving the block does.

From the keyboard, press **Enter** or **Space** while the block is selected to open it, then use the usual block navigation to reach what you want.

You can tell the two states apart: a protected block shows a pointer cursor when you hover it, and whichever block you currently have selected is tinted.

The List view is never restricted. You can always expand a guarded block there and select any inner block directly, which is often the easiest way to reach something deep inside a modal. Selecting an inner block that way opens the block too.

Moving a guarded block re-protects it. If you had opened a block and then dragged it somewhere else, double-click it again to carry on editing inside.

Blocks using Block Guard:

- Add to Calendar
- Online Event
- RSVP
- RSVP Response
- Venue

While the Venue block is protected, the Venue Map inside it hides its resize handles. Open the Venue block to resize the map.

### How is this different from WordPress's block locking?

They solve opposite problems. Core's block locking pins a block in place — it prevents moving or removing the block, but still lets you click inside and edit its inner blocks. Block Guard leaves the block free to move as a whole, while protecting its insides from accidental selection and editing until you double-click in. Block Guard also never actually locks anything: the block stays fully editable, it just asks for one deliberate gesture first. Use core locking when a block must stay where it is; use Block Guard to keep a complex block's inner structure intact while you work around it. The two can be combined.
