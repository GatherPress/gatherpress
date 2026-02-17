# Venue-V2 Block

...

## Showing a Venue

...

### Showing a Venue inside an Event Post

Probably the most important use-case, is to show venue details on an event page or in any kind of event (query) list.

**The block is a dynamic and context-aware block**. This means, the block shows the venue of the current event. This is the same in the editor, in the frontend, in singular views or used within a query block.

### Showing a Venue inside an Event Query

...

### Showing a Venue inside any other post type

Used in any other context than an event post, the block works more as a portal block.

The block still allows to select a venue, but instead of acting on the post level, this time the selected venue is saved into the blocks attributes. **The block is now a dynamic and context-unaware block**.

## Creating a Venue

Using this block an editor can create a new Venue (post) instantly, while editing or creating an event.

Clicking the "Add new Venue" Button next to the venue select field will reveal a simple form to create a venue.
A title & the full address of a new venue are saved by the block and the new venue will be pre-selected automatically.

The "Add new Venue" Button is not available

- to users without the capability to create Venues in general
- inside a query block
