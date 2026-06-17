# GatherPress

<!-- markdownlint-disable-next-line MD045 -->
![](.wordpress-org/banner-1544x500.jpg)

**GatherPress is a flexible, community-powered event management plugin for WordPress.**

[![Try it in WordPress Playground](https://img.shields.io/badge/Try_it-in_WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9)][blueprint-nightly] ![Version](https://img.shields.io/static/v1?label=version&message=0.34.0-beta.1&color=blue)

[![GPLv2 License](https://img.shields.io/github/license/GatherPress/gatherpress)](https://github.com/GatherPress/gatherpress/blob/main/LICENSE) [![Coding Standards](https://github.com/GatherPress/gatherpress/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/coding-standards.yml) [![PHPUnit Tests](https://github.com/GatherPress/gatherpress/actions/workflows/phpunit-tests.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/phpunit-tests.yml) [![JavaScript Unit Tests](https://github.com/GatherPress/gatherpress/actions/workflows/jest-tests.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/jest-tests.yml) [![E2E Tests](https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml)

## Screenshots

1. Creating a new event
   ![screenshot-1](.wordpress-org/screenshot-1.png)
2. Editing an event
   ![screenshot-2](.wordpress-org/screenshot-2.png)
3. Settings screen
   ![screenshot-3](.wordpress-org/screenshot-5.png)

## Features

- Event scheduling (date, time, location, description)
- Attendee registration (with optional anonymous listing)
- Open RSVP support (non-logged-in users)
- Attendees can bring guests
- Email notifications for attendees and non-attendees
- Online and in-person event support (with mapping)
- Full block editor support
- Multisite-ready and fully internationalized

## Getting Started

### Try it instantly

Use the [Playground Environment][blueprint-nightly] to test GatherPress with real data -- no setup required.

[Watch the intro demo](https://gatherpress.org/demovideo) | [Learn more about Playground](https://github.com/GatherPress/gatherpress/blob/main/docs/playground.md)

### Install from WordPress.org

1. Go to **Plugins > Add New**
2. Search for `GatherPress`
3. Click **Install**, then **Activate**

### Install from GitHub

Download the latest `.zip` from the [Releases page](https://github.com/GatherPress/gatherpress/releases), then upload via **Plugins > Add New > Upload Plugin**.

[Installation guide](https://github.com/GatherPress/gatherpress/blob/main/docs/installation.md) | [Configuration guide](https://github.com/GatherPress/gatherpress/blob/main/docs/configuration.md)

## Get Involved

GatherPress is built by and for the community -- contributions are always welcome.

- Read the [Developer Docs](https://github.com/GatherPress/gatherpress/tree/develop/docs/developer)
- Check out [open issues](https://github.com/GatherPress/gatherpress/issues)
- Join us on [WordPress Slack](https://make.wordpress.org/chat/) or [GatherPress.org](https://gatherpress.org/get-involved)

[Contributor Guide](https://github.com/GatherPress/gatherpress/blob/main/docs/contributing.md)

## Third-Party Libraries

- [Leaflet](https://leafletjs.com/) -- interactive maps for venues

## External Services

GatherPress calls the following services when editing venues or displaying maps. Each can be overridden or swapped out via a filter; see links for filter names.

- **[Photon](https://photon.komoot.io/)** (operated by Komoot) -- powers address autocomplete and geocoding. The plugin's REST routes (`/gatherpress/v1/geocode` and `/gatherpress/v1/geocode/search`) proxy the editor's query to Photon server-side with a User-Agent identifying the site. Responses are cached as transients for 15 minutes. Override with the `gatherpress_photon_api_url` filter (for a self-hosted Photon, for example).
- **[CARTO Basemaps](https://carto.com/basemaps/)** -- default map tile provider when the OpenStreetMap map platform is selected. The visitor's browser loads raster tiles directly from `basemaps.cartocdn.com`. Override with `gatherpress_interactive_map_tile_url` and `gatherpress_interactive_map_tile_attribution`.
- **[Google Maps](https://www.google.com/maps/)** -- alternative map platform, enabled when a site chooses "Google Maps" in GatherPress settings. The visitor's browser embeds `maps.google.com`.
- Map and address data are sourced from [OpenStreetMap contributors](https://www.openstreetmap.org/copyright).

## More Information

- [Changelog](https://github.com/GatherPress/gatherpress/releases)
- [Frequently Asked Questions](https://github.com/GatherPress/gatherpress/blob/main/docs/faq.md)
- [Alpha plugin info](https://github.com/GatherPress/gatherpress-alpha)

---

*GatherPress is still in active development. Thank you for helping us build a better way to gather.*

[blueprint-nightly]: https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/GatherPress/gatherpress/main/.wordpress-org/blueprints/blueprint-nightly.json