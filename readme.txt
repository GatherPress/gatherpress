=== GatherPress ===
Contributors:      mauteri, hrmervin, jmarx, meaganhanes, pbrocks
Tags:              events, event, meetup, community
License:           GNU General Public License v2.0 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tested up to:      6.4
Stable tag:        0.28.0

GatherPress, powering our community's event management needs.

== Description ==

GatherPress, a plugin created by and for the WordPress community, is a response to the community's desire for novel event management tools that meet the diverse needs of event organizers and members. Its agenda and roadmap align with that of the WordPress community, ensuring that it evolves in tandem with our collective wants and needs.

This project is for the collaborative effort to build a compelling event management application using open source tools such as _WordPress_ and _BuddyPress_ and the grit sweat and love of **the community, for the community**.

We're creating the very network features we need to host events and gather well.

== Features ==

- Event Scheduling: set dates, times, and provide event information details.
- Attendee registration.
- Ability for attendees to be listed anonymously (only administrators will see their names).
- Emailing system: to send emails to all the group members, or a specific event attendees, non-attendees, and those on the waiting list.
- In person events: add the venue, with an optional map (refer to point 4)
- Online event management: add the video meeting URL.
- Multi-event management: capability to handle multiple events simultaneously.
- Multisite environment: This setup allows for centralized management while providing flexibility for each site to host its own unique events with its settings (language, timezone, date time format) and set of users.
- Works with blocks.
- Fully internationalized.
- Freedom to add content besides the default event/venue blocks, to remove default blocks, and add synced patterns (useful for adding consistent information across all events).

= Upcoming features =

- Allow attendees to add guests.
- Import events from meetup.com with an add-on plugin (currently in development).
- Recurring events.
- Calendar block.
- Email notification when event starts.

== Installation ==

1. Download the plugin: you'll find the latest release on the GatherPress GitHub repository, under [Releases](https://github.com/GatherPress/gatherpress/releases) > Assets and download `gatherpress.zip`.
2. Install it in your WordPress instance: go to WP Admin Plugins > Add new plugin. Choose the `gatherpress.zip` file you just downloaded.
3. Activate the plugin.

== How to Use ==

= Configure GatherPress =

In WP Admin, go to `Events`  > `Settings`.

You can change different settings such as:
- Show publish date as event date for events.
- The default maximum limit of attendees to an event.
- Anonymous RSVP.
- Date Format.
- Time Format.
- Display the timezone for scheduled events.
- Upcoming Events page.
- Past Events page.

= Create an Event =

In WP Admin, go to `Events` > `Add New`.

By default, a few blocks are populated, you can keep them or delete them and you can add more blocks.

- The Event date block allows you to define the start and end dates and times of your event, as well as the timezone.
- The Add to Calendar block enables your users to add the event to their preferred calendar directly from the published event on the frontend.
- The Venue block lets you choose whether your event is online or in a venue. You can select the venue (refer to point 4) and the map settings: display, zoom level, type of map and map height.
- The RSVP block enables members to confirm they attend or do not attend an event.
- The description of the event is a normal paragraph block. You can add anything with any block here.
- The RSVP Response block displays a list of members who have confirmed they attend or do not attend an event.
- The event settings allow you to modify all the above mentioned settings, enable or disable anonymous RSVP, choose Topics, notify members or attendees, as well as standard WordPress settings such as the featured image, the excerpt, allow or disallow comments, etc.

= Create a Venue =

In WP Admin, go to `Events` > `Venues`.

In the Venue block, you can define:
- The full address, telephone, and website of the venue.
- The map settings.

= Create an Event Topic =

In WP Admin, go to `Events`  > `Topics`.

Topics are like post categories, but for events.

== Requirements ==

To run GatherPress, we recommend your host supports:

* PHP version 7.4 or greater.
* MySQL version 5.6 or greater, or, MariaDB version 10.0 or greather.
* HTTPS support.

== Installation ==

1. Download the plugin: you'll find the latest release on the GatherPress GitHub repository, under [Releases](https://github.com/GatherPress/gatherpress/releases) > Assets and download `gatherpress.zip`.
2. Install it in your WordPress instance: go to WP Admin Plugins > Add new plugin. Choose the `gatherpress.zip` file you just downloaded.
3. Activate the plugin.

== Contribute ==

If you wish to share in the collaborative of work to build _GatherPress_, please drop us a line either via [WordPress Slack](https://make.wordpress.org/chat/) or on [GatherPress.org](htps://gatherpress.org/get-involved).

= Collaborator Access =

To get write access to the GitHub repo, please reach out to our **GitHub Administrators**: [Mervin Hernandez](https://github.com/MervinHernandez) and [Mike Auteri](https://github.com/mauteri).

To get access to [GatherPress.org](htps://gatherpress.org/get-involved) via SSH or WP Admin login, please reach out to our **GatherPress.org Administrator**: [Mervin Hernandez](https://github.com/MervinHernandez).

== Developer Documentation ==

Our developer documentation can be found in [our GitHub repository here](https://github.com/GatherPress/gatherpress?tab=readme-ov-file#developer-documentation).

== Screenshots ==

@TODO

== Changelog ==

See complete changelog at https://github.com/GatherPress/gatherpress/releases.

== Upgrade Notice ==

= 1.0.0 =
See: https://gatherpress.org/releases/version-1-0-0
