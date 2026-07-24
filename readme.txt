=== GatherPress ===
Contributors: mauteri, patricia70, hrmervin, jmarx75, stephenerdelyi, carstenbach, supernovia
Tags: events, rsvp, meetup, community, calendar
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.35.0-alpha.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build events, together. Open source event tools for the communities that run on WordPress.

== Description ==

Running a meetup, a user group, a conference, or a class? GatherPress gives you what you need to publish events, collect RSVPs, and keep everyone in the loop, all from your own WordPress site.

Your events live on your site. You own the data, you control how it looks, and you keep the relationship with the people who show up.

GatherPress is built by the WordPress community for the people who organize it: meetup organizers, WordCamp teams, user groups, and anyone else who gets people together in a room. It is free, open source, and shaped by the people using it every day.

= What you can do =

- Publish events with the date, time, venue, and everything people need to know
- Collect RSVPs, with anonymous listing for attendees who would rather not be named
- Let people RSVP without an account, so nobody bounces off a signup wall
- Let attendees bring guests
- Email everyone who is coming, or everyone who is not, without leaving WordPress
- Run events in person, online, or both, with maps for physical venues
- Build and arrange it all in the block editor
- Multisite ready and fully translatable

= Try it before you install =

Open a working demo in your browser through WordPress Playground. No signup, no setup, and real data to click around in.

[Try GatherPress in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/GatherPress/gatherpress/develop/.wordpress-org/blueprints/blueprint.json) | [Watch the intro demo](https://gatherpress.org/demovideo)

= Join our community =

GatherPress is built in the open, and contributions are always welcome, whether that is code, translations, documentation, or just telling us what broke.

- [Get involved](https://gatherpress.org/get-involved)
- [Contributor guide](https://github.com/GatherPress/gatherpress/blob/main/docs/contributing.md)
- [Open issues on GitHub](https://github.com/GatherPress/gatherpress/issues)

== Installation ==

1. Go to **Plugins > Add New**
2. Search for `GatherPress`
3. Click **Install**, then **Activate**

== Screenshots ==

1. Creating a new event
2. Editing an event
3. Settings screen

== Changelog ==

For the full changelog, visit the [GitHub releases page](https://github.com/GatherPress/gatherpress/releases).

== Frequently Asked Questions ==

Visit our [FAQ page](https://github.com/GatherPress/gatherpress/blob/main/docs/faq.md) for answers to common questions.

== External Services ==

This plugin connects to external services to provide address autocomplete, geocoding, and map tiles for venues. Each service has its own filter so you can point at a self-hosted alternative or disable the feature.

= Photon geocoding (photon.komoot.io) =

Used to resolve venue addresses into latitude/longitude coordinates and to power the address autocomplete dropdown while editing a venue. The plugin proxies these requests server-side: your WordPress site sends the search query string and a User-Agent identifying the site (plugin version, WordPress version, site home URL) to the Photon API. Responses are cached on your site for 15 minutes.

- Photon homepage / terms: https://photon.komoot.io/
- Komoot privacy policy: https://www.komoot.com/privacy

Override the endpoint (for a self-hosted Photon instance) with the `gatherpress_photon_api_url` filter.

= CARTO Basemaps (basemaps.cartocdn.com) =

Default map tile provider when the OpenStreetMap map platform is selected. When a venue map is displayed on the front end or in the editor, the visitor's browser requests raster tiles directly from basemaps.cartocdn.com. No data beyond the standard tile coordinates (zoom, x, y) and the visitor's IP / browser headers is sent.

- CARTO terms: https://carto.com/legal/
- CARTO privacy policy: https://carto.com/privacy/

Override the tile URL or attribution with the `gatherpress_interactive_map_tile_url` and `gatherpress_interactive_map_tile_attribution` filters.

= Google Maps (maps.googleapis.com, www.google.com, maps.google.com) =

Alternative map provider, only used when a site opts in by choosing "Google Maps" in GatherPress settings. What the visitor's browser requests depends on whether a Google Maps API key is configured:

- With an API key, the browser loads the Maps JavaScript API from maps.googleapis.com and renders the map in the page.
- Without a key, or if that script fails to load, the browser embeds a Google Maps iframe from www.google.com or maps.google.com instead.

Either way the request carries the venue coordinates, the visitor's IP, and standard browser headers. A configured API key travels with the request and is visible in the page source, so restrict it by HTTP referrer in Google Cloud.

- Google Maps terms: https://cloud.google.com/maps-platform/terms/
- Google privacy policy: https://policies.google.com/privacy

Map and address data used by Photon and CARTO are sourced from OpenStreetMap contributors (https://www.openstreetmap.org/copyright).
