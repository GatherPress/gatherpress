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

Complex blocks like RSVP are built from many inner blocks (buttons, modals, text). With Block Guard enabled, the whole block behaves as a single unit: you can select it, move it, and style it, but you cannot click into its inner structure. To customize the inner blocks, toggle Block Guard off in the block's settings sidebar first — the toggle's help text always tells you which state you are in.

Keep Block Guard enabled if you only need the standard layout. Toggle it off only when you intentionally want to adjust a block's inner layout, and consider re-enabling it when you are done.

Blocks using Block Guard:

- Add to Calendar
- Online Event
- RSVP
- RSVP Response
- Venue

### How is this different from WordPress's block locking?

They solve opposite problems. Core's block locking pins a block in place — it prevents moving or removing the block, but still lets you click inside and edit its inner blocks. Block Guard leaves the block free to move and restyle as a whole, while protecting its insides from accidental selection and editing. Use core locking when a block must stay where it is; use Block Guard to keep a complex block's inner structure intact while you work around it. The two can be combined.
