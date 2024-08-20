# GatherPress

Stable tag: 0.30.0  
Tested up to: 6.6.1  
License: GPL v2 or later  
Tags: events, event, meetup, community  
Contributors: mauteri, hrmervin, patricia70, carstenbach, jmarx75, stephenerdelyi, calebthedev, prayagm, pbrocks, linusx007

![](.wordpress-org/banner-1544x500.jpg)

GatherPress, powering our community's event management needs.

![GPLv2 License](https://img.shields.io/github/license/GatherPress/gatherpress) [![Coding Standards](https://github.com/GatherPress/gatherpress/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/coding-standards.yml) [![PHPUnit Tests](https://github.com/GatherPress/gatherpress/actions/workflows/phpunit-tests.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/phpunit-tests.yml) [![JavaScript Unit Tests](https://github.com/GatherPress/gatherpress/actions/workflows/jest-tests.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/jest-tests.yml) [![E2E Tests](https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml) [![SonarCloud](https://github.com/GatherPress/gatherpress/actions/workflows/sonarcloud.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/sonarcloud.yml) [![Dependency Review](https://github.com/GatherPress/gatherpress/actions/workflows/dependency-review.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/dependency-review.yml)

[![WordPress.org plugin directory guidelines](https://github.com/GatherPress/gatherpress/actions/workflows/wordpress-org-plugin-guidelines.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/wordpress-org-plugin-guidelines.yml) [![Playground Demo Link](https://img.shields.io/badge/WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/GatherPress/gatherpress/main/.wordpress-org/blueprints/blueprint.json)

![WordPress Plugin Required PHP Version](https://img.shields.io/wordpress/plugin/required-php/gatherpress) ![WordPress Plugin: Required WP Version](https://img.shields.io/wordpress/plugin/wp-version/gatherpress) ![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/gatherpress) ![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/gatherpress) [![WordPress version checker](https://github.com/GatherPress/gatherpress/actions/workflows/wordpress-version-checker.yml/badge.svg)](https://github.com/GatherPress/gatherpress/actions/workflows/wordpress-version-checker.yml)

![WordPress Plugin Active Installs](https://img.shields.io/wordpress/plugin/installs/gatherpress) ![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/gatherpress) ![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/rating/gatherpress)

## Description

GatherPress, a plugin created by and for the WordPress community, is a response to the community's desire for novel event management tools that meet the diverse needs of event organizers and members. Its agenda and roadmap align with that of the WordPress community, ensuring that it evolves in tandem with our collective wants and needs.

**We propose a pilot program to test GatherPress, a community-developed plugin, within interested and active WordPress meetup groups. This initiative stems from our community’s need for an innovative event management tool tailored to the unique demands of WordPress event organizers and participants.**
[@Patricia BT](https://profiles.wordpress.org/patricia70/) in January 2024 on [make.wordpress.org](https://make.wordpress.org/community/2024/01/22/proposal-pilot-program-to-test-gatherpress-on-the-wordpress-org-network-as-a-meetup-alternative/)

This project is for the collaborative effort to build a compelling event management application using open source tools such as _WordPress_ and _BuddyPress_ and the grit sweat and love of **the community, for the community**.

We're creating the very network features we need to host events and gather well.

https://www.youtube.com/watch?v=BnYS36C5d38&t=2s


### Features

- Event Scheduling: set dates, times, and provide event information details.
- Attendee registration.
- Allow attendees to add guests.
- Ability for attendees to be listed anonymously (only administrators will see their names).
- Emailing system: to send emails to all the group members, or a specific event attendees, non-attendees, and those on the waiting list.
- In person events: add the venue, with an optional map (refer to point 4)
- Online event management: add the video meeting URL.
- Multi-event management: capability to handle multiple events simultaneously.
- Multisite environment: This setup allows for centralized management while providing flexibility for each site to host its own unique events with its settings (language, timezone, date time format) and set of users.
- Works with blocks.
- Fully internationalized.
- Freedom to add content besides the default event/venue blocks, to remove default blocks, and add synced patterns (useful for adding consistent information across all events).

### Upcoming features

- Import events from meetup.com with an add-on plugin. ([#](https://github.com/GatherPress/gatherpress/issues/394))
- Recurring events. ([#](https://github.com/GatherPress/gatherpress/issues/80))
- Calendar block. ([#](https://github.com/GatherPress/gatherpress/issues/369))
- Email notification when event starts. ([#](https://github.com/GatherPress/gatherpress/issues/429))
- Event federation using ActivityPub ([#](https://github.com/GatherPress/gatherpress/issues/569))

### Contribute

If you wish to share in the collaborative of work to build GatherPress, please drop us a line either via [WordPress Slack](https://make.wordpress.org/chat/) or on [GatherPress.org](https://gatherpress.org/get-involved). The development location of the GatherPress project can be found at [https://github.com/gatherpress/gatherpress](https://github.com/gatherpress/gatherpress). All contributions are welcome: code, design, user interface, documentation, [translation](https://translate.wordpress.org/projects/wp-plugins/gatherpress/) and more.

### Third-Party Libraries

This plugin leverages the following third-party libraries for various functionalities:

- [React-Modal](https://github.com/reactjs/react-modal): Facilitates the creation of modal dialogs in React components.
- [React-Tooltip](https://github.com/wwayne/react-tooltip): Provides customizable tooltips for React applications.
- [Leaflet](https://leafletjs.com/): Provides global, open-source mapping functionality

## Screenshots

1. Create a new event  
   ![Create a new event](.wordpress-org/screenshot-1.png)
2. Create a new venue  
   ![Create a new venue](.wordpress-org/screenshot-2.png)
3. General Settings  
   ![General Settings](.wordpress-org/screenshot-3.png)
4. Leadership Settings  
   ![Leadership Settings](.wordpress-org/screenshot-4.png)

## Installation

1. Download the plugin: you'll find the latest release on the GatherPress GitHub repository, under [Releases](https://github.com/GatherPress/gatherpress/releases) > Assets and download `gatherpress.zip`.
2. Install it in your WordPress instance: go to WP Admin Plugins > Add new plugin. Choose the `gatherpress.zip` file you just downloaded.
3. Activate the plugin.

### Requirements

To run GatherPress, we recommend your host supports:

- PHP version 7.4 or greater.
- MySQL version 5.6 or greater, or, MariaDB version 10.0 or greather.
- HTTPS support.

## Frequently Asked Questions

### What external services are used in GatherPress?

- Mapping Services: We use OpenStreetMap and Google Maps to display meeting locations on a map. To achieve this, we send the address to OpenStreetMap or Google Maps for rendering.
- Calendar Integration: GatherPress also supports "Add to Calendar" functionality using Google Calendar and Yahoo! Calendar.

### Configure GatherPress

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

### Create an Event

In WP Admin, go to `Events` > `Add New`.

By default, a few blocks are populated, you can keep them or delete them and you can add more blocks.

- The Event date block allows you to define the start and end dates and times of your event, as well as the timezone.
- The Add to Calendar block enables your users to add the event to their preferred calendar directly from the published event on the frontend.
- The Venue block lets you choose whether your event is online or in a venue. You can select the venue (refer to point 4) and the map settings: display, zoom level, type of map and map height.
- The RSVP block enables members to confirm they attend or do not attend an event.
- The description of the event is a normal paragraph block. You can add anything with any block here.
- The RSVP Response block displays a list of members who have confirmed they attend or do not attend an event.
- The event settings allow you to modify all the above mentioned settings, enable or disable anonymous RSVP, choose Topics, notify members or attendees, as well as standard WordPress settings such as the featured image, the excerpt, allow or disallow comments, etc.

### Create a Venue

In WP Admin, go to `Events` > `Venues`.

In the Venue block, you can define:
- The full address, telephone, and website of the venue.
- The map settings.

### Create an Event Topic

In WP Admin, go to `Events`  > `Topics`.

Topics are like post categories, but for events.

## Contribute

If you wish to share in the collaborative of work to build _GatherPress_, please drop us a line either via [WordPress Slack](https://make.wordpress.org/chat/) or on [GatherPress.org](htps://gatherpress.org/get-involved). The development location of the GatherPress project can be found at [https://github.com/gatherpress/gatherpress](https://github.com/gatherpress/gatherpress). All contributions are welcome: code, design, user interface, documentation, translation, and more.

### Collaborator Access

To get write access to the GitHub repo, please reach out to our **GitHub Administrators**: [Mervin Hernandez](https://github.com/MervinHernandez) and [Mike Auteri](https://github.com/mauteri).

To get access to [GatherPress.org](htps://gatherpress.org/get-involved) via SSH or WP Admin login, please reach out to our **GatherPress.org Administrator**: [Mervin Hernandez](https://github.com/MervinHernandez).

### Read Developer Documentation

Find the developer documentation inside the plugins' `docs` folder.

### What’s about the PRO version?

As a Community powered plugin, GatherPress is already the PRO-version.

Because we strive for close-to-core development, love decisions - not options and follow a lot of well known best-practices within the WordPress space, we can and do focus on what matters most - powering our community's event management needs.

GatherPress‘ best-practices:

- Tested & validated against [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Not only once, but consequently tested & validated against the [WordPress plugin review guidelines](https://github.com/WordPress/plugin-check-action).
- [JavaScript & PHP Unit tests](https://sonarcloud.io/summary/new_code?id=GatherPress_gatherpress&branch=main) are covering almost 80% of the whole codebase.
- Import & Export event- and venue-data using WordPress' native tools.

### Reminder that GatherPress is still in Alpha

As we continue to refine and develop the plugin, please use the [GatherPress Alpha](https://github.com/GatherPress/gatherpress-alpha) plugin alongside the core GatherPress plugin. The Alpha plugin manages breaking changes easily: just make sure it is up-to-date, activate it, go to the Alpha section under GatherPress Settings, and click "Fix GatherPress!" after updating GatherPress. This process helps us avoid technical debt as we work towards launching version 1.0.0 of the plugin.

## Changelog

See complete changelog at https://github.com/GatherPress/gatherpress/releases.

## Upgrade Notice

### 1.0.0

=======
### Is GatherPress WordPress Multisite compatible?
Yes, GatherPress can be run on a network of sites. The additional database tables it needs, will be created automatically for each new site if the plugin is network-activated.

GatherPress can also be activated per site.

### What’s about the PRO version?

As a Community powered plugin, GatherPress is already the PRO-version.

Because we strive for close-to-core development, love decisions - not options and follow a lot of well known best-practices within the WordPress space, we can and do focus on what matters most - powering our community's event management needs.

GatherPress‘ best-practices:

- Tested & validated against [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Not only once, but consequently tested & validated against the [WordPress plugin review guidelines](https://github.com/WordPress/plugin-check-action).
- [JavaScript & PHP Unit tests](https://sonarcloud.io/summary/new_code?id=GatherPress_gatherpress&branch=main) are covering almost 80% of the whole codebase.

### Reminder that GatherPress is still in Alpha

As we continue to refine and develop the plugin, please use the [GatherPress Alpha](https://github.com/GatherPress/gatherpress-alpha) plugin alongside the core GatherPress plugin. The Alpha plugin manages breaking changes easily: just make sure it is up-to-date, activate it, go to the Alpha section under GatherPress Settings, and click "Fix GatherPress!" after updating GatherPress. This process helps us avoid technical debt as we work towards launching version 1.0.0 of the plugin.

## Changelog

See complete changelog at https://github.com/GatherPress/gatherpress/releases.

## Upgrade Notice

### 1.0.0

See: https://gatherpress.org/releases/version-1-0-0
