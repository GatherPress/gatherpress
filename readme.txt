=== GatherPress ===
Contributors: mauteri, patricia70, hrmervin, jmarx75, stephenerdelyi, carstenbach, jordanpak, mahimadave, tusharaddweb, pkbhatt, supernovia, fahimmurshed
Tags: events, event, meetup, community
Requires at least: 7.0
Tested up to: 7.0.2
Requires PHP: 8.1
Stable tag: 0.34.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GatherPress is a flexible, community-powered event management plugin for WordPress.

== Description ==

- Event scheduling (date, time, location, description)
- Attendee registration (with optional anonymous listing)
- Open RSVP support (non-logged-in users)
- Attendees can bring guests
- Email notifications for attendees and non-attendees
- Online and in-person event support (with mapping)
- Full block editor support
- Multisite-ready and fully internationalized

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

= Google Maps (maps.google.com) =

Alternative map provider, only used when a site opts in by choosing "Google Maps" in GatherPress settings. When enabled, the visitor's browser embeds a Google Maps iframe.

- Google Maps terms: https://cloud.google.com/maps-platform/terms/
- Google privacy policy: https://policies.google.com/privacy

Map and address data used by Photon and CARTO are sourced from OpenStreetMap contributors (https://www.openstreetmap.org/copyright).
