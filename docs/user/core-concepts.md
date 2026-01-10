# Core concepts

This section explains the fundamental ideas behind GatherPress. Understanding these concepts will make event creation, display, and management much easier.

## Events

An event is the central content type in GatherPress. Each event represents a real-world or online happening with a defined date, time, and (optionally) a venue.

Key points:

* Events are created as a dedicated event post type, not regular posts or pages.  
* Events combine content (blocks you add in the editor) and structured data (date, time, venue, RSVP settings).  
* Events can be drafts, scheduled, or published, and can be upcoming or past events.
* Event can have Topics, which work like Categories for posts.

* [More about Events](./creating-and-managing-events.md)
* [More about Topics](./topics.md)

## Venues

A venue describes where an event takes place.

Venues are separate from events so they can be reused and managed independently.

It usually defines a physical venues, with an address and optional location details.

Note: 

Online links, such as video calls or livestreams, should be directly set in the event, not under venues.

Key points:

* One venue can be reused across multiple events.  
* Updating a venue automatically affects all events using it.  
* Events can exist without a venue if needed.

[More about Venues](./venues.md)

## Dates and times

Every event has at least one date and time, the next day at 6:00pm if filled by default.

Supported patterns:

* Single-date events  
* Multi-date events, for now you must fill the end date and time to the end of the event for it to span several days. Recurring events are to be set manually for now. It’s on our roadmap to include recurring functionality.

Notes:

* Dates and times are stored in a structured way.  
* Timezones are handled automatically based on site settings and user profile.  
* Front-end display adapts to the visitor’s locale when possible.

## Attendees and RSVPs

An RSVP represents a person registering their intention to attend an event.

An attendee is the stored record of that RSVP.

RSVP behavior:

* An RSVP can come from a logged-in user or a visitor without a WordPress account.  
* Attendees are managed per event.  
* Events can have attendance limits.  
* RSVPs can be managed manually by event organizers.  
* There is no payment or ticketing mechanism.

[More about RSVPs](./rsvp-system.md)

## Content blocks and event data

GatherPress blocks allow you to display and manage:

   * Date, time and timezone  
   * Venue or online link  
   * RSVP settings  
   * Optional guest number and capacity  
   * Stored as structured data, can be used for for filtering, sorting, and querying events
   * Additional content such as text, images, lists, and other blocks you add in the editor  

[More about blocks](./blocks.md)

