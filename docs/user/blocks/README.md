# Blocks

[Event Query](./event-query.md)

Event List (will be deprecated in the next version, 0.34)

[RSVP and its inner blocks](./rsvp-and-inner-blocks.md) (RSVP Form and fields, Modal Manager, etc)

[RSVP Response and its inner blocks](./rsvp-response-and-inner-blocks.md) (RSVP Response Toggle, Avatar Display Name, RSVP Guest Count Display, etc)

Add to Calendar: Allows a user to add an event to their preferred calendar application.

### Other blocks used in an event

See details on [Creating and managing events](../creating-and-managing-events.md)

Event Date
Venue
Online Event

## Block Guard

Some GatherPress block use the Block Guard mechanism, which protects from accidentally editing their inner blocks.

While Block Guard is enabled, those inner blocks are protected and you cannot freely edit their structure.  If you want to customize the inner blocks, you must toggle off the block guard for the parent block first.

Keep Block Guard enabled if you only need the standard layout. Toggle it off only when you intentionally want to adjust the RSVP block’s inner layout.

Blocks using Block guard:

- Add to Calendar
- RSVP
- RSVP Response
- In 0.34 (next version), it will also be applied to the "Venue" block