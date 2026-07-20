# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries for the next release live as individual files under
[`.github/changelog/`](.github/changelog/) and get rolled up into a new
version section by `composer changelog:write` at release time.

## [0.34.1] - 2026-07-20
### Fixed
- Fixed a bug in the modal manager block where keyboard navigation (using the Enter or Space key) failed to open or close the modal on non-anchor trigger wrappers due to missing Interactivity API event handlers.

## [0.34.0] - 2026-07-10
### Security
- Enforce per-post edit capability on venue / event meta and post-specific REST routes so unauthenticated or under-privileged callers cannot modify another user's events or venues. [#1520] [#1917]
- Lock down `GITHUB_TOKEN` permissions across CI workflows to least-privilege scopes, reducing the blast radius of a compromised workflow. [#1650] [#1917]
- Override vulnerable transitive npm dependencies (`qs`, `uuid`, `webpack-dev-server`) via npm overrides. [#1651] [#1917]
- Rate-limit the geocoding REST endpoints to prevent abuse-by-loop. [#1546] [#1917]
- Require the `promote_users` capability (not just per-event `edit_post`) before adding another user to the site when RSVPing them into an event, so editors and authors cannot enroll arbitrary users as subscribers — most impactful on multisite. Self-RSVP auto-join is unchanged. [#1839] [#1917]
- Split privileged PR workflows to remove the untrusted-checkout / cache-poisoning exposure that pull-request-target workflows otherwise inherit. [#1652] [#1917]

### Added
- Add a block transform from `core/post-date` to `gatherpress/event-date`, re-implemented via the WordPress Block Transforms API. [#1565] [#1664] [#1917]
- Add a configurable RSVP mode with a sitewide disable switch and per-event control, plus an Open RSVP setting that lets non-logged-in users RSVP via magic-link comments. [#1468] [#1469] [#1917]
- Add a default template for the Event Query Loop block so it has a sensible starting point on themes that don't ship one. [#1399] [#1917]
- Add a setting for the Google Maps API key, so sites that prefer Google over OpenStreetMap can wire credentials in through the UI. [#1568] [#1917]
- Add a site health test that flags installs running on plain permalinks, since several GatherPress features (notably RSVP magic links and .ics downloads) require pretty permalinks. [#1660] [#1917]
- Add a `gatherpress.durationDefault` JavaScript filter to override the default event duration (in hours). The value is honored when it matches one of the available `durationOptions`, otherwise it falls back to the first available duration. [#1706] [#1917]
- Add a `gatherpress_geocode_street_line` filter to reorder the house number and street in geocoded address suggestion labels (e.g. `Hauptstraße 42` instead of `42 Hauptstraße`), so sites can match locale address conventions. [#1836] [#1917]
- Add a `show_if` field key to the Settings API so settings fields can be conditionally hidden based on the value of other fields. [#1634] [#1917]
- Added user documentation for the subscribable iCal calendar feeds. [#1917]
- Add network-wide GatherPress settings with per-option inheritance so multisite installs can share or override individual settings at the site level. [#1500] [#1917]
- Add server-rendered static maps to the venue-map block, with a regenerate button, prewarming, retina (2×) srcset rendering, a scale attribute, and Inspector polish. Editor previews now poll for async-arriving map descriptors so the block reflects geocode-on-save state in real time. [#1480] [#1483] [#1485] [#1489] [#1491] [#1492] [#1497] [#1498] [#1917]
- Add starter pattern pickers to the Event, Venue, RSVP Form, RSVP Response, and RSVP blocks, plus an Event Query Loop starter pattern with a Start blank event scaffold. Patterns are hookable so themes can register their own. [#1571] [#1578] [#1579] [#1580] [#1581] [#1582] [#1584] [#1917]
- Add tag-driven release automation. Pushing a stable tag now triggers a workflow that builds a versioned distro zip, rolls up `.github/changelog/*` into a new `CHANGELOG.md` section (auto-PR'd back to develop), creates a GitHub Release with the zip attached, and deploys to wordpress.org. Pre-release tags (alpha/beta/rc) produce a GitHub Pre-Release with the `[Unreleased]` changelog snapshot and skip the wp.org deploy. See `docs/developer/release-process.md`. [#1917]
- Add the Event Archive setting so site organizers can choose whether the post-type archive defaults to upcoming, past, or is disabled. [#1587] [#1917]
- Add the `gatherpress-event-date` post type support so any post type can opt into event datetime storage, the `gatherpress_events` DB table, date-based query ordering, and the Event Date / Add to Calendar blocks. [#1440] [#1917]
- Add the `gatherpress-rsvp` post type support so any post type can opt into the comment-based RSVP system, attendee management, waiting lists, and the RSVP block family. [#1444] [#1917]
- Add the `gatherpress-venue` and `gatherpress-online-event` post type supports so venue association and online-event link handling can be enabled on any custom event post type. [#1453] [#1917]
- Add venue address autocomplete search powered by the Photon geocoder, with a rate-limited REST endpoint and graceful fallback to manual entry. [#1451] [#1546] [#1917]
- Adopt the Jetpack changelogger workflow with a per-PR `.github/changelog/*` file format and a rolled-up `CHANGELOG.md`. Backfills history from 0.27.0. [#1917]
- Documented the GDPR opt-in steps for event update emails in the privacy docs. [#1917]
- Expose the `gatherpress_venue_map_prewarm_pre_enqueue_job` filter so extensions can short-circuit individual venue-map prewarm jobs (useful for sites with hundreds of venues where a `switch_theme` would otherwise flood WP-Cron with warm jobs). [#1504] [#1917]
- Expose WordPress Geodata standard meta on venue post types so third-party themes and plugins that consume `geo_latitude` / `geo_longitude` / `geo_address` work out of the box. [#1471] [#1917]
- Extract the `gatherpress-shadow-source` primitive. Hidden `_<post_type>` taxonomies with post-name-derived terms can now be enabled on any post type, providing the foundation for the venue ⇄ event tagging mechanism and letting companion plugins register their own source-style CPTs (productions, organizers, etc.). [#1569] [#1917]
- Integrate with the Advanced Query Loop plugin, including a Venue facet in the taxonomy filter. [#1411] [#1917]
- New documentation about customizable Playground previews per pull-request [#1917]
- Persist structured venue address fields (house number, street, city, county, state, postcode, country, country code) via an async geocode-on-save cron handler. Exposed via individual meta keys for block bindings. [#1517] [#1530] [#1917]
- Refactor the Settings API with flat storage, a config-driven architecture, import / export, and reorganized tabs. [#1434] [#1917]
- Support the Interactivity API's `clientNavigation` flag for the venue block so navigation between venue pages stays SPA-style on themes that opt in. [#1464] [#1917]

### Changed
- Apply the GatherPress hook naming convention across the codebase. New `shadow_*` subsystem row added to the hooks-naming-convention doc. [#1550] [#1917]
- Apply the WordPress 6.8 `__next40pxDefaultSize` opt-in to every `SelectControl` instance so admin form controls match the upcoming default sizing. [#1661] [#1917]
- Bump 'Tested up to' to WordPress 7.0. [#1649] [#1917]
- Calendar endpoint registration is now an explicit `(new Foo(...))->init()` chain instead of a side effect of `new Foo(...)`. The `Calendar\\Endpoint` constructor only stores validated args; `init()` performs rewrite-rule registration and hook wiring, and returns `$this` for chaining. Fixes five SonarCloud `php:S1848` bugs flagged on `Calendar\\Setup` and makes the registration side effect surface at the call site. [#1917]
- Each post type supporting RSVPs now gets its own RSVPs admin page, scoped to that post type. [#1917]
- Enforce per-post edit capability on venue / event meta and post-specific REST routes. [#1520] [#1917]
- Extend the admin list-table columns, sorting, and views to every event-supporting post type — not just the built-in `gatherpress_event`. [#1467] [#1619] [#1917]
- Extract `Event\Meta` and `Venue\Meta` from their respective `Setup` classes so meta registration lives in a dedicated, testable component. [#1542] [#1917]
- Flatten `Event_*` classes into the `GatherPress\Core\Event` subnamespace. `Event_Admin_List` is extracted into its own class. [#1450] [#1508] [#1917]
- Flatten `Rsvp_*` classes into the `GatherPress\Core\Rsvp` subnamespace. [#1524] [#1917]
- Flatten `Venue_*` classes into the `GatherPress\Core\Venue` subnamespace. [#1522] [#1917]
- GatherPress UI strings now pull from each post type's registered labels rather than hardcoding 'Event' / 'Venue'. Custom event-supporting post types see their own labels everywhere in the settings sub-menus, admin list, sidebar panels, and Query block UI. [#1627] [#1629] [#1631] [#1647] [#1659] [#1917]
- Generalize the `gatherpress/venue` block and the `gatherpress/event-query` block's contextual toggle to work with any `gatherpress-shadow-source` post type (productions, tours, organizers, etc.), not just venues. Adds a new `gatherpress_shadow_taxonomy_object_types` filter so extensions declaratively wire their shadow taxonomy to event CPTs, plus two reusable `Shadow_Source` helpers (`resolve_post_from_query_context`, `build_tax_query_clause`) for custom Query Loops. [#1917]
- Honor the WordPress comment privacy filters when inserting RSVP comments. [#1470] [#1917]
- Include `SECURITY.md` in the distributed plugin zip. `wp-scripts plugin-zip` ignores `.distignore` and builds from a fixed allowlist, so a `package.json` `files` list now pins the shipped fileset and adds the security policy. [#1917]
- Lowered the default Venue Map zoom level from 18 to 16 so new venue maps show more neighborhood context. Sites that already set a custom Default Zoom Level are unaffected. [#1917]
- Make `postIdOverride` work for the event and venue blocks, with the dim-gate suppressed on non-event hosts when the override resolves. [#1552] [#1554] [#1917]
- Move RSVP-related settings to a dedicated RSVP settings panel and relocate the online event link to Venue settings for better organization. [#1394] [#1917]
- Move the gatherpress-utility-style enqueue onto `enqueue_block_assets` so it reaches the editor iframe in FSE themes. [#1655] [#1917]
- Re-implement block-unregistration handling and clean up legacy unregister flow. [#1409] [#1917]
- Re-implement the calendar .ics download endpoint with proper rewrites and feed handling, replacing the legacy Event ICS code. [#1669] [#1917]
- Read `isEditorPanelOpened` from `core/editor` instead of `core/edit-post`, silencing a WordPress 6.5 deprecation warning. [#1653] [#1917]
- Refactor the venue-detail block architecture and fix context handling so it composes cleanly inside Query Loops and FSE templates. [#1391] [#1405] [#1917]
- Refactor the venue map block into a swappable provider strategy so Google Maps and OpenStreetMap (and future providers) share a common interface. [#1534] [#1917]
- Rename the `safeHTML` helper to `stripScriptsAndEventHandlers` and document its narrow scope (it strips `<script>` tags and `on*=` event handlers — it is not a full HTML sanitizer). [#1545] [#1917]
- Rename `OnlineEventLink` to `OnlineEvent` and remove the legacy Listener / Broadcaster code that was no longer used. [#1420] [#1917]
- Replace the original RSVP / RSVP Response / RSVP Template / Venue blocks with their V2 equivalents that shipped in 0.32.0. The V2 naming is dropped now that the new blocks are canonical. [#1407] [#1917]
- Restructure settings tabs — Date & Time moves under Events, Maps moves under a new Venues tab. `Venue` splits into an instance class plus a `Venue_Setup` singleton. [#1477] [#1917]
- Separate `README.md` (GitHub-facing) and `readme.txt` (wp.org-facing) so each surface gets content tailored to its audience. [#1423] [#1917]
- Stop dimming GatherPress blocks inside Query Loop contexts when valid event context is present. [#1549] [#1917]
- Support arbitrarily deep namespaces in the GatherPress class autoloader. [#1526] [#1917]
- The Default Map Type venue setting now only appears when the Google Maps platform is selected. [#1917]
- The editor's "already passed" notice now uses the post type's own singular label, so a custom event-supporting post type (e.g. a `production`) reads "Production has already passed." instead of the hardcoded "event". [#1917]
- Updated Settings screens and Editor UI to better reflect the registered post type & taxonomy labels. [#1917]
- Updated the configuration guide for the 0.34.0 settings screens. [#1917]
- Updated the features list and static-map docs for 0.34.0. [#1917]
- Use the WordPress-native taxonomy column for venues in the events admin list, replacing the custom column. [#1544] [#1917]
- Verified and updated the RSVP system docs for the shipped 0.34.0 behavior. [#1917]
- WordPress conventions sweep across the codebase (blank line after class openings per WP core style, JS docblock dependency-header normalization, `str_contains` / `str_starts_with` migration from `strpos`), plus two small settings panel fixes. [#1589] [#1917]

### Removed
- Remove the unused public `event/events-list` REST endpoint (and its `events_list`/`max_number` handlers). The route had no callers in the plugin or build output and exposed event RSVP data on a public, unauthenticated GET; the same data is already rendered on public event pages, so nothing relied on it. [#1917]
- Remove the `window.GatherPress` global in favor of editor settings, interactivity state, and per-block data stores. [#1465] [#1917]

### Fixed
- Add the `gatherpress_exclude_rsvp_from_comment_query` filter so integrations (like comment moderation plugins) can opt back into seeing RSVP comments in the comment query. [#1640] [#1917]
- Center the RSVP Response display name again on WordPress versions where the comment author name block moved text alignment to the typography block support. [#1917]
- Deduplicate admin column sorting in `Event_Setup` and fix SQL identifier quoting on the sort query. [#1449] [#1917]
- Editor no longer forces the events admin list back to the Upcoming filter when the user explicitly selects All — the previously-saved filter sticks. [#1515] [#1917]
- Event Query block no longer lists past events on the front end when its saved query omits the event type; it now defaults to upcoming, matching the editor. [#1806] [#1917]
- Event Query blocks placed in a Single Event block template now filter by the viewed event's season/venue and can exclude the current event on the frontend, instead of rendering every event unfiltered. [#1917]
- Event Query blocks placed inside a block template no longer leave the editor preview spinning indefinitely. [#1917]
- Fix a datetime-picker stack overflow when pressing the down-arrow on the year field in relative mode. [#1621] [#1917]
- Fix anonymous RSVP deletion breaking magic-link tokens. [#1424] [#1917]
- Fix Dropdown block not resetting the trigger label or selected index when the default selected item is deleted in Select Mode. [#1917]
- Fixed the event-date block incorrectly showing the edited event's dates for all events inside a query loop on an event post. [#1917]
- Fixed the Event Query block hanging on an endless spinner when its post type is switched to one that does not support event dates. [#1917]
- Fix ICS export escaping of lowercase `n` characters in event titles and descriptions. [#1416] [#1917]
- Fix initial context for event date supporting types inside venue block [#1917]
- Fix misleading single-event iCal URL in calendar test fixtures. [#1730] [#1917]
- Fix the default archive temporal handling (upcoming / past / none) for event-supporting custom post types. The `gatherpress_event_archive_mode` filter receives the queried post type as its second argument. [#1624] [#1917]
- Fix the Event Query Settings panel and REST collection params so they work for any event-supporting post type. [#1622] [#1917]
- Fix the orderby behavior of the default Past events filter. [#1442] [#1917]
- Fix the Post Date block to display the event date when the 'use event date for post date' setting is enabled. [#1430] [#1917]
- Fix timezone parsing so option values can no longer leak HTML attributes into rendered markup. [#1558] [#1917]
- Fix venue sorting in the admin venues list. [#1448] [#1917]
- Follow-up to the 0.33.3 protected-meta hardening. Additional GatherPress meta keys are now protected from the Custom Fields panel to prevent stale data overwrites on event save. [#1387] [#1917]
- Gate the 'Send Updates' email notice so it only appears on event-supporting post types, and surface post-type-aware label / pattern filters for related UI. [#1598] [#1917]
- Guard the RSVP block against a malformed `serializedInnerBlocks` attribute so a bad stored value degrades gracefully instead of crashing the editor with "RSVP block has encountered an error and cannot be previewed". [#1704] [#1713] [#1917]
- Harden asset metadata loading so build dependency/version data is read correctly even if the asset file was already loaded in the request. [#1917]
- Hide the Filter by Current Venue toggle when the host post type doesn't support venues, and gate the Event Query Settings panel by context. [#1566] [#1917]
- Hide the RSVPs admin menu when no post type supports the RSVP feature. [#1917]
- Invalidate caches on RSVP token redemption and bypass page cache for magic-link URLs so the user sees the result of their RSVP immediately. [#1636] [#1917]
- Link PR numbers in CHANGELOG.md to their pull requests. [#1917]
- Make the Online Event Link field a URL input and add spacing between the toggle and the field [#1917]
- Multiple JS fixes for blocks inside FSE templates. [#1459] [#1461] [#1917]
- Only show "Venue Settings" panel for post types supporting `gatherpress-venue-information` [#1917]
- Open RSVP attendees are now correctly promoted from the waiting list when a spot opens up. [#1917]
- Pre-release tags (alpha / beta / rc) now produce a release body containing the rolled-up changelog of every queued entry, computed in an ephemeral checkout so the entry files stay in place for the eventual stable release. Previously the body only showed CHANGELOG.md's `[Unreleased]` section, which is empty until the stable rollup happens, so testers downloading pre-release zips never saw what they were testing. [#1917]
- Prevent a fatal error when the event REST response is prepared without an id field (e.g. a _fields= request that excludes it). [#1917]
- Prevent a successful RSVP from showing a false "RSVP API request failed" alert and a broken modal when the block's state markup is malformed or incomplete. [#1917]
- Read SVGs from the filesystem instead of issuing an HTTP self-request, fixing icon-block rendering on hosts behind authenticating reverse proxies. [#1455] [#1917]
- Refuse activation when duplicate GatherPress folders are on disk to prevent silent class collisions. [#1560] [#1917]
- Remove the dead `RichText` `onSplit` prop on the dropdown-item block, silencing a WordPress 6.4 deprecation warning. [#1663] [#1917]
- Render the Duration select with whatever duration options are provided, defaulting to the first available option when the built-in 2-hour default is filtered out via `gatherpress.durationOptions`, instead of dropping straight to the end-time picker. [#1706] [#1917]
- Replace custom-made "Start blank" logic with registerBlockVariation() calls, like it is intended by WP core. [#1917]
- Return 404 for the event archive URL when no matching page exists, instead of rendering an empty archive. [#1381] [#1917]
- RSVP requests no longer throw and leave the UI inconsistent when the server returns a success response with a missing or partial responses object. [#1917]
- RSVP status changes now save correctly from event archive and Query Loop pages, not only from individual event pages. [#1917]
- Scope the RSVP UI to the `gatherpress-rsvp` support so non-RSVP event post types don't see RSVP fields. [#1594] [#1917]
- Set `referrerPolicy: 'no-referrer-when-downgrade'` on OpenStreetMap tile requests so tile providers that gate access on the `Referer` header (e.g. CARTO basemaps) receive the request correctly. [#1433] [#1917]
- Several improvements and bug fixes to the Event Query Loop block surface (template handling, attribute migration, pattern selection). [#1396] [#1398] [#1917]
- Show "Exclude current event" toggle only when queried post type matches the currently edited post type. [#1917]
- Show a clear "Map could not be loaded" state instead of a blank gray box when the interactive map's tile provider fails to serve tiles (e.g. CartoDB returning 502s for uncached high-zoom tiles). A failed tile fetch no longer leaves the map silently empty. [#1917]
- Stopped the RSVP attendance modal from flickering when the Number of Guests field was changed. [#1917]
- Stop the front-end RSVP button from vanishing when a request fails or the block markup is incomplete. The status switcher now falls back to the no_status state when no inner block matches, the request handler guards against missing/non-JSON responses and surfaces an error instead of failing silently, and the post-RSVP modal logic no longer throws on malformed markup. [#1917]
- Stop the venue map from breaking out of the body on narrow viewports. With an auto width, the inline `height` + `aspect-ratio` pair resolved to a fixed pixel width (300px tall at 2/1 → 600px wide) that overflowed mobile screens; the wrapper is now capped to its container width. [#1703] [#1917]
- The event-date block inside a venue block with a source post type (e.g. productions/seasons) now shows the related source post's date instead of the host event's date in the editor. [#1917]
- The events list REST endpoint now always returns an array for the current_user field instead of an empty string when no RSVP exists. [#1917]
- The RSVP manager in the editor now shows an error notice instead of silently clearing the attendee list when an RSVP request fails. [#1917]
- The venue-map block returned new empty-object references from its useSelect selector on every render, triggering Gutenberg's "Non-equal value keys" warning and causing unnecessary re-renders when no venue address was set. [#1917]

## [0.33.3] - 2026-02-16
### Fixed
- Protect GatherPress meta from the Custom Fields panel to prevent stale data overwrites on event save. [#1383](https://github.com/GatherPress/gatherpress/pull/1383)

## [0.33.2] - 2026-02-08
### Fixed
- Fix "not allowed to edit custom field" error when publishing events. [#1375](https://github.com/GatherPress/gatherpress/pull/1375)

## [0.33.1] - 2026-01-30
### Fixed
- Fix `.ics` downloads on sites with custom permalinks, auto-flush rewrite rules on plugin update, and resolve add-to-calendar block context inside Query Loops. [#1356](https://github.com/GatherPress/gatherpress/pull/1356)

## [0.33.0] - 2026-01-10
### Added
- New `gatherpress/event-query` block variation of `core/query` so theme authors can build event-aware loops from any template. [#962](https://github.com/GatherPress/gatherpress/pull/962)
- New `gatherpress/rsvp-form` block with token-based authentication, anonymous submissions, guest counts, and configurable form-field blocks. [#1106](https://github.com/GatherPress/gatherpress/pull/1106) [#1116](https://github.com/GatherPress/gatherpress/pull/1116) [#1168](https://github.com/GatherPress/gatherpress/pull/1168) [#1179](https://github.com/GatherPress/gatherpress/pull/1179)
- New `gatherpress/add-to-calendar` block with cleaner architecture, replacing the legacy attendee-list-only Add to Calendar markup. [#1068](https://github.com/GatherPress/gatherpress/pull/1068)
- Block Guard feature for query-loop integration so editor previews stay interactive without breaking save flow. [#1159](https://github.com/GatherPress/gatherpress/pull/1159)
- Auto-generated developer hook docs under `docs/developer/hooks/`, regenerated by CI on every push to `develop`. [#1135](https://github.com/GatherPress/gatherpress/pull/1135)
- Display options for the Event Date block (date format, time format, separator). [#1155](https://github.com/GatherPress/gatherpress/pull/1155)
- Filter `gatherpress_default_duration_options` to customize the duration dropdown options. [#882](https://github.com/GatherPress/gatherpress/pull/882)
- RSS feed for past events. [#1189](https://github.com/GatherPress/gatherpress/pull/1189)
- Loading animation while the RSVP API is in flight. [#1208](https://github.com/GatherPress/gatherpress/pull/1208)
- GDPR-compliant opt-in filter for event notifications. [#1225](https://github.com/GatherPress/gatherpress/pull/1225)
- Improved security policy with detailed reporting guidelines. [#1321](https://github.com/GatherPress/gatherpress/pull/1321)

### Changed
- Refactor RSVP form processing into a centralized helper class; UI polish across the RSVP block family. [#1224](https://github.com/GatherPress/gatherpress/pull/1224)
- Refactor CSS class naming across blocks to a consistent component pattern. [#1183](https://github.com/GatherPress/gatherpress/pull/1183)
- Improve autoloader flexibility by removing hardcoded directory mappings. [#1199](https://github.com/GatherPress/gatherpress/pull/1199)
- Tie submitted RSVP emails to existing users when possible; fix comment approval flow. [#1213](https://github.com/GatherPress/gatherpress/pull/1213)
- Add support for WordPress 6.9. [#1236](https://github.com/GatherPress/gatherpress/pull/1236)
- Add support for PHP 8.4 in the test matrix. [#1147](https://github.com/GatherPress/gatherpress/pull/1147)
- Move build directory out of the repository in favor of release artifacts. [#1066](https://github.com/GatherPress/gatherpress/pull/1066)
- Update `register_setting` calls to use sanitize callbacks. [#1074](https://github.com/GatherPress/gatherpress/pull/1074)
- Replace deprecated WordPress admin notice markup with `wp_admin_notice()`. [#1080](https://github.com/GatherPress/gatherpress/pull/1080)
- Simplify rewrite rules flushing by leveraging WordPress core's mechanism. [#1231](https://github.com/GatherPress/gatherpress/pull/1231)
- Add gesture-handling library so Leaflet maps don't hijack page scroll. [#1089](https://github.com/GatherPress/gatherpress/pull/1089)
- Update RSVP column on the admin events list. [#1237](https://github.com/GatherPress/gatherpress/pull/1237)
- Add post-type-aware event validation and past-event handling for the RSVP form visibility system. [#1239](https://github.com/GatherPress/gatherpress/pull/1239)
- Multisite test coverage added to the suite. [#1291](https://github.com/GatherPress/gatherpress/pull/1291)

### Fixed
- Resolve translation-loading error by moving GP core function to the `init` hook. [#1055](https://github.com/GatherPress/gatherpress/pull/1055)
- Allow multiple `gatherpress/venue` blocks per event. [#1095](https://github.com/GatherPress/gatherpress/pull/1095)
- Fix critical error when WPForms Pro is also installed. [#1118](https://github.com/GatherPress/gatherpress/pull/1118)
- Fix Block Guard behavior in FSE templates. [#1119](https://github.com/GatherPress/gatherpress/pull/1119)
- Fix Block Guard inside query-loop blocks. [#1159](https://github.com/GatherPress/gatherpress/pull/1159)
- Fix RSVP block dimming, focus trap, and button-block targeting. [#1244](https://github.com/GatherPress/gatherpress/pull/1244) [#1256](https://github.com/GatherPress/gatherpress/pull/1256) [#1269](https://github.com/GatherPress/gatherpress/pull/1269)
- Restrict the RSVP "view" filter to RSVP comment types so counts and the "Mine" filter are correct. [#1133](https://github.com/GatherPress/gatherpress/pull/1133)
- Prevent generic comment notifications from being sent to event authors when an RSVP comment is left. [#1218](https://github.com/GatherPress/gatherpress/pull/1218)
- Honor WordPress comment privacy filters on RSVP inserts. [#1470](https://github.com/GatherPress/gatherpress/pull/1470)
- Fix date-picker relative-mode behavior so duration offset is maintained when the start date changes. [#1219](https://github.com/GatherPress/gatherpress/pull/1219)
- Fix Event Query block toggle on hosts where `array_filter` was stripping `0` values. [#1235](https://github.com/GatherPress/gatherpress/pull/1235)
- Fix Moment Timezone error for sites configured with a Manual UTC Offset. [#1336](https://github.com/GatherPress/gatherpress/pull/1336)
- Fix UI/UX bug where dropdown children were not auto-expanding on parent select. [#1333](https://github.com/GatherPress/gatherpress/pull/1333)
- Fix hardcoded comment-type assumptions so third-party comment plugins keep working. [#1226](https://github.com/GatherPress/gatherpress/pull/1226)
- Fix `extract-wp-hooks` action when no new hooks are present. [#1230](https://github.com/GatherPress/gatherpress/pull/1230)
- Don't ship the `.wordpress-org` directory in plugin releases. [#1104](https://github.com/GatherPress/gatherpress/pull/1104)

### Removed
- Remove `window.GatherPress` global in favor of editor settings, interactivity state, and data stores. [#1465](https://github.com/GatherPress/gatherpress/pull/1465)

## [0.32.3] - 2025-07-09
### Fixed
- Maintenance release with PHP / WordPress compatibility fixes and translation updates carried forward from `develop`. [#1117](https://github.com/GatherPress/gatherpress/pull/1117)

## [0.32.2] - 2025-05-01
### Fixed
- Maintenance release rolling up post-0.32.1 fixes. [#1072](https://github.com/GatherPress/gatherpress/pull/1072)

## [0.32.1] - 2025-04-23
### Added
- `author` support for the event and venue post types so post author appears in block bindings and admin UI. [#1050](https://github.com/GatherPress/gatherpress/pull/1050) [#1052](https://github.com/GatherPress/gatherpress/pull/1052)

### Changed
- Composer dependency refresh. [#1058](https://github.com/GatherPress/gatherpress/pull/1058)

## [0.32.0] - 2025-04-12
### Added
- V2 of the RSVP and RSVP Response blocks with a templating system for attending / not-attending / waiting-list / past / no-status states. [#959](https://github.com/GatherPress/gatherpress/pull/959)
- RSVP Anonymous Checkbox block with per-event opt-in. [#979](https://github.com/GatherPress/gatherpress/pull/979)
- RSVP guest feature with configurable per-event guest count. [#975](https://github.com/GatherPress/gatherpress/pull/975)
- Save RSVP commenter IP address. [#1008](https://github.com/GatherPress/gatherpress/pull/1008)
- Block Guard primitive that lets RSVP and RSVP Response blocks integrate cleanly with the Query Loop block. [#1023](https://github.com/GatherPress/gatherpress/pull/1023)
- Dynamic login and registration links so the RSVP login prompt respects WordPress core settings. [#1027](https://github.com/GatherPress/gatherpress/pull/1027)
- New "Add to calendar" block variation. [#954](https://github.com/GatherPress/gatherpress/pull/954)
- New calendar REST endpoints with documentation and unit tests. [#927](https://github.com/GatherPress/gatherpress/pull/927) [#928](https://github.com/GatherPress/gatherpress/pull/928) [#929](https://github.com/GatherPress/gatherpress/pull/929)
- Hookable patterns infrastructure with slot fills and developer docs. [#888](https://github.com/GatherPress/gatherpress/pull/888)
- New block variation loading mechanism. [#898](https://github.com/GatherPress/gatherpress/pull/898)
- Toggle for showing/hiding latitude and longitude on the Venue block. [#877](https://github.com/GatherPress/gatherpress/pull/877)
- Portuguese (Brazil) locale added to the screenshot generator. [#963](https://github.com/GatherPress/gatherpress/pull/963)
- Block context now propagates to the Event Date block. [#990](https://github.com/GatherPress/gatherpress/pull/990)

### Changed
- RSVP storage migrates from the custom `gp_rsvps` table to the WordPress `comments` table, simplifying queries and aligning with WP conventions. [#692](https://github.com/GatherPress/gatherpress/pull/692)
- Make event query respect `orderBy`, `order`, and "unfinished events". [#889](https://github.com/GatherPress/gatherpress/pull/889)
- Rename "responses" to "records" so the word isn't repeated in data structures. [#997](https://github.com/GatherPress/gatherpress/pull/997)
- Unit test coverage increased substantially across Event, RSVP, Block Guard, Dropdown_Item, Validate, General_Block, Modal, Settings, Event Query, RSVP Query, and global JS helpers. [#1002](https://github.com/GatherPress/gatherpress/pull/1002) [#1003](https://github.com/GatherPress/gatherpress/pull/1003) [#1007](https://github.com/GatherPress/gatherpress/pull/1007) [#1010](https://github.com/GatherPress/gatherpress/pull/1010) [#1012](https://github.com/GatherPress/gatherpress/pull/1012) [#1013](https://github.com/GatherPress/gatherpress/pull/1013) [#1014](https://github.com/GatherPress/gatherpress/pull/1014) [#1015](https://github.com/GatherPress/gatherpress/pull/1015) [#1017](https://github.com/GatherPress/gatherpress/pull/1017) [#1018](https://github.com/GatherPress/gatherpress/pull/1018) [#1031](https://github.com/GatherPress/gatherpress/pull/1031)
- Use focus-trap helper on the RSVP modal so keyboard navigation stays inside the dialog. [#985](https://github.com/GatherPress/gatherpress/pull/985)
- Update Add to Calendar icon styling. [#1004](https://github.com/GatherPress/gatherpress/pull/1004)
- Remove global JS file and rework into per-block helpers. [#986](https://github.com/GatherPress/gatherpress/pull/986)
- Make the login / registration links dynamic. [#1027](https://github.com/GatherPress/gatherpress/pull/1027)
- Node version + package updates. [#1029](https://github.com/GatherPress/gatherpress/pull/1029)

### Removed
- Remove `initialDecline` functionality. [#1019](https://github.com/GatherPress/gatherpress/pull/1019)
- Comment out legacy Add to Calendar code (replaced by new block, see 0.33.0). [#987](https://github.com/GatherPress/gatherpress/pull/987)

### Fixed
- Fix the waiting-list check when max attendance is `0` (interpreted as unlimited). [#999](https://github.com/GatherPress/gatherpress/pull/999)
- Fix dropdown bug in the RSVP Response block. [#1034](https://github.com/GatherPress/gatherpress/pull/1034)
- Fix focusable elements inside Block Guard overlay. [#1035](https://github.com/GatherPress/gatherpress/pull/1035)
- Fix event panels not loading on truly-new events. [#1028](https://github.com/GatherPress/gatherpress/pull/1028)
- Fix regex potentially vulnerable to super-linear runtime due to backtracking. [#1032](https://github.com/GatherPress/gatherpress/pull/1032)
- Many a11y improvements (modal aria, focus traps, color contrast). [#972](https://github.com/GatherPress/gatherpress/pull/972)

## [0.31.0] - 2024-10-04
### Added
- Customizable rewrite bases for post types and taxonomies. [#812](https://github.com/GatherPress/gatherpress/pull/812)
- New `gatherpress/event-date` block UI with refactored controls and renamed files for consistency. [#820](https://github.com/GatherPress/gatherpress/pull/820) [#873](https://github.com/GatherPress/gatherpress/pull/873)
- Customizable `Event->get_formatted_datetime()` (now public). [#878](https://github.com/GatherPress/gatherpress/pull/878)
- New developer docs section for running PHPUnit via `wp-env`. [#879](https://github.com/GatherPress/gatherpress/pull/879)
- Refactor REST API validation into its own class. [#883](https://github.com/GatherPress/gatherpress/pull/883)
- PHPStan static analysis with 36 small bug fixes uncovered by the new gate. [#931](https://github.com/GatherPress/gatherpress/pull/931) [#938](https://github.com/GatherPress/gatherpress/pull/938)
- 100% unit-test coverage for the `Topic` class. [#932](https://github.com/GatherPress/gatherpress/pull/932)
- New WordPress.org README validation workflow. [#811](https://github.com/GatherPress/gatherpress/pull/811)
- New typo-checking workflow. [#824](https://github.com/GatherPress/gatherpress/pull/824)
- New automated image-compression workflow for screenshots. [#837](https://github.com/GatherPress/gatherpress/pull/837) [#914](https://github.com/GatherPress/gatherpress/pull/914)
- Migrate the screenshot workflow to playground/cli with multi-locale support. [#838](https://github.com/GatherPress/gatherpress/pull/838) [#892](https://github.com/GatherPress/gatherpress/pull/892)
- Conditionally render the featured image in event-notification emails. [#918](https://github.com/GatherPress/gatherpress/pull/918)
- Set `html` language attributes correctly on outbound emails. [#919](https://github.com/GatherPress/gatherpress/pull/919)
- Respect the user's locale and custom date/time formats in sent emails. [#922](https://github.com/GatherPress/gatherpress/pull/922)
- Allow escaped characters in user-defined date/time format strings. [#920](https://github.com/GatherPress/gatherpress/pull/920) [#637](https://github.com/GatherPress/gatherpress/pull/637)

### Changed
- Rename "REST API" class to "Event REST API" for clarity. [#864](https://github.com/GatherPress/gatherpress/pull/864)
- Replace "Open Street Maps" with "OpenStreetMap" throughout (typo prevention). [#881](https://github.com/GatherPress/gatherpress/pull/881) [#890](https://github.com/GatherPress/gatherpress/pull/890)
- Use the latest stable plugin version when generating screenshots (not `dev`). [#847](https://github.com/GatherPress/gatherpress/pull/847)
- Updated PHPCS rules and applied fixes. [#897](https://github.com/GatherPress/gatherpress/pull/897)
- Hide alpha-plugin admin notice when installed via the branch-named zip. [#808](https://github.com/GatherPress/gatherpress/pull/808)
- Cleanup and remove outdated code. [#925](https://github.com/GatherPress/gatherpress/pull/925)

### Fixed
- Resolve race condition between Playground setup and Playwright start. [#905](https://github.com/GatherPress/gatherpress/pull/905)
- Fix Sidebar / Event / Venue panels opening behavior by default. [#935](https://github.com/GatherPress/gatherpress/pull/935)
- Fix focus on the message textarea after pressing the "Compose message" button. [#829](https://github.com/GatherPress/gatherpress/pull/829)
- Various GitHub Actions workflow fixes. [#813](https://github.com/GatherPress/gatherpress/pull/813) [#814](https://github.com/GatherPress/gatherpress/pull/814) [#817](https://github.com/GatherPress/gatherpress/pull/817) [#840](https://github.com/GatherPress/gatherpress/pull/840) [#843](https://github.com/GatherPress/gatherpress/pull/843)

## [0.30.0] - 2024-08-15
### Added
- OpenStreetMap support as an alternative to Google Maps. [#643](https://github.com/GatherPress/gatherpress/pull/643)
- Export & Import functionality. [#655](https://github.com/GatherPress/gatherpress/pull/655)
- WordPress Playground preview for the plugin (with pull-request preview support). [#664](https://github.com/GatherPress/gatherpress/pull/664) [#666](https://github.com/GatherPress/gatherpress/pull/666)
- "GatherPress Alpha" admin notice for users running pre-release builds. [#739](https://github.com/GatherPress/gatherpress/pull/739)
- New filter to filter events in the admin list table by venue. [#695](https://github.com/GatherPress/gatherpress/pull/695)
- URL encoding on "Add to calendar" links so titles with special characters survive. [#725](https://github.com/GatherPress/gatherpress/pull/725)
- Site-administrator notifications appear in multisite admin too. [#785](https://github.com/GatherPress/gatherpress/pull/785)
- Membership check includes a timezone check. [#672](https://github.com/GatherPress/gatherpress/pull/672)
- Test scripts for event scenarios. [#713](https://github.com/GatherPress/gatherpress/pull/713) [#721](https://github.com/GatherPress/gatherpress/pull/721)
- Multilingual screenshot generation via Playwright. [#783](https://github.com/GatherPress/gatherpress/pull/783)

### Changed
- Use the WordPress `comments` table for RSVPs instead of the custom RSVP table (foundation for later RSVP work). [#692](https://github.com/GatherPress/gatherpress/pull/692)
- Reflect changes to the venue term when a venue post gets deleted. [#731](https://github.com/GatherPress/gatherpress/pull/731)
- Update autoloader. [#733](https://github.com/GatherPress/gatherpress/pull/733)
- User documentation expanded. [#780](https://github.com/GatherPress/gatherpress/pull/780) [#781](https://github.com/GatherPress/gatherpress/pull/781) [#782](https://github.com/GatherPress/gatherpress/pull/782)
- Cleanup of language files / methods (handled via WordPress core mechanisms). [#718](https://github.com/GatherPress/gatherpress/pull/718)
- Don't load admin CSS/JS on every wp-admin request. [#654](https://github.com/GatherPress/gatherpress/pull/654)
- Limit when `admin_notices` render (skip REST requests). [#745](https://github.com/GatherPress/gatherpress/pull/745)
- Show admin notices in relevant spots and in multisite admin. [#785](https://github.com/GatherPress/gatherpress/pull/785)

### Fixed
- Fix multisite activation. [#685](https://github.com/GatherPress/gatherpress/pull/685)
- Fix max attendance edge cases. [#701](https://github.com/GatherPress/gatherpress/pull/701)
- Fix public requests of the shadow venue taxonomy. [#677](https://github.com/GatherPress/gatherpress/pull/677)
- Switch from `rewrite => false` to `publicly_queryable => false` on the venue taxonomy. [#678](https://github.com/GatherPress/gatherpress/pull/678)
- Fix venues label. [#788](https://github.com/GatherPress/gatherpress/pull/788)
- Fix venue context resolution. [#791](https://github.com/GatherPress/gatherpress/pull/791)
- Plugin preview is configured to use modern Admin UI. [#823](https://github.com/GatherPress/gatherpress/pull/823)

## [0.29.3] - 2024-06-27
### Fixed
- Fix render-file paths for wp.org review. [#706](https://github.com/GatherPress/gatherpress/pull/706)
- Update CLI to align with wp.org plugin guidelines. [#707](https://github.com/GatherPress/gatherpress/pull/707)
- Fix last render issue. [#710](https://github.com/GatherPress/gatherpress/pull/710)

## [0.29.2] - 2024-06-21
### Fixed
- Fix the max-attendance setting (it was not honored correctly). [#698](https://github.com/GatherPress/gatherpress/pull/698)

## [0.29.1] - 2024-06-11
### Fixed
- Fix table creation issue on multisite installs. [#679](https://github.com/GatherPress/gatherpress/pull/679)

## [0.29.0] - 2024-06-04
### Added
- RSVP guest feature returns (with configurable guest count). [#570](https://github.com/GatherPress/gatherpress/pull/570)
- Maximum attending limit at the event level. [#581](https://github.com/GatherPress/gatherpress/pull/581)
- User-format and timezone defaults pulled from WordPress core options. [#556](https://github.com/GatherPress/gatherpress/pull/556) [#617](https://github.com/GatherPress/gatherpress/pull/617)
- Decline-attendance flow that allows users to decline immediately. [#567](https://github.com/GatherPress/gatherpress/pull/567)
- Plugin banner images. [#580](https://github.com/GatherPress/gatherpress/pull/580)
- Allow the date- and time-format strings to contain escaped characters. [#637](https://github.com/GatherPress/gatherpress/pull/637)
- WP-CLI i18n tooling for translators. [#585](https://github.com/GatherPress/gatherpress/pull/585)
- WordPress.org plugin-guidelines check action. [#616](https://github.com/GatherPress/gatherpress/pull/616)
- Sanitize nonce checks. [#644](https://github.com/GatherPress/gatherpress/pull/644)
- Respect user locale across the UI. [#747](https://github.com/GatherPress/gatherpress/pull/747)
- Spanish translation update for v0.28 follow-up. [#594](https://github.com/GatherPress/gatherpress/pull/594)

### Changed
- Decouple and add user filters across the plugin so themes can override behavior. [#579](https://github.com/GatherPress/gatherpress/pull/579)
- Improve translations across multiple languages. [#588](https://github.com/GatherPress/gatherpress/pull/588)
- Updated `gatherpress_` prefix consistency. [#645](https://github.com/GatherPress/gatherpress/pull/645)
- Update "past" wording to "passed" where appropriate. [#642](https://github.com/GatherPress/gatherpress/pull/642)
- Bump blocks to bust admin cache on gatherpress.org. [#648](https://github.com/GatherPress/gatherpress/pull/648)
- Updated documentation re: publicly documenting filters. [#647](https://github.com/GatherPress/gatherpress/pull/647)

### Fixed
- Make online-link available after a user RSVPs attending. [#597](https://github.com/GatherPress/gatherpress/pull/597)
- Fix bug with guest settings. [#575](https://github.com/GatherPress/gatherpress/pull/575)
- Fix issue with default user-datetime fallback. [#576](https://github.com/GatherPress/gatherpress/pull/576)
- Fix RSVP Response block in FSE. [#578](https://github.com/GatherPress/gatherpress/pull/578)
- Fix SQL calls to use the `%i` placeholder. [#622](https://github.com/GatherPress/gatherpress/pull/622)
- Fix markup issue with a self-closing `<div>` tag. [#619](https://github.com/GatherPress/gatherpress/pull/619)
- Change default venue address; do not display the default address on the frontend. [#591](https://github.com/GatherPress/gatherpress/pull/591)
- Fix event-in-the-past link issue. [#661](https://github.com/GatherPress/gatherpress/pull/661)
- Fix language inconsistencies. [#590](https://github.com/GatherPress/gatherpress/pull/590)

### Security
- Disallow direct file access across all plugin PHP files. [#624](https://github.com/GatherPress/gatherpress/pull/624)

## [0.28.0] - 2024-02-19
### Added
- Anonymous RSVP option so attendees can opt out of being listed publicly. [#513](https://github.com/GatherPress/gatherpress/pull/513)
- Datetime caching for performance. [#518](https://github.com/GatherPress/gatherpress/pull/518)
- Datetime formatting on the Events List block. [#517](https://github.com/GatherPress/gatherpress/pull/517)
- RSVP form token field. [#522](https://github.com/GatherPress/gatherpress/pull/522)
- User-profile checkbox to opt in or out of event email notifications. [#531](https://github.com/GatherPress/gatherpress/pull/531)
- FSE compatibility for all blocks. [#529](https://github.com/GatherPress/gatherpress/pull/529)
- Repository docs: Code of Conduct, license declarations, repo templates, dependency review, WordPress version checker. [#500](https://github.com/GatherPress/gatherpress/pull/500) [#501](https://github.com/GatherPress/gatherpress/pull/501) [#502](https://github.com/GatherPress/gatherpress/pull/502) [#503](https://github.com/GatherPress/gatherpress/pull/503) [#504](https://github.com/GatherPress/gatherpress/pull/504) [#505](https://github.com/GatherPress/gatherpress/pull/505)
- WordPress.org deploy workflow. [#512](https://github.com/GatherPress/gatherpress/pull/512)
- E2E test login-flow assertion. [#509](https://github.com/GatherPress/gatherpress/pull/509)
- German (`de_*`) translations. [#543](https://github.com/GatherPress/gatherpress/pull/543)

### Changed
- Refactor localized data delivery. [#530](https://github.com/GatherPress/gatherpress/pull/530)
- Set anonymous RSVP to `false` by default. [#532](https://github.com/GatherPress/gatherpress/pull/532)
- Rename "attend" to "no_status" to better reflect default state. [#525](https://github.com/GatherPress/gatherpress/pull/525)
- Surface `settings_errors()` on the GatherPress settings template. [#520](https://github.com/GatherPress/gatherpress/pull/520)
- Spanish (v0.28) translation refresh. [#552](https://github.com/GatherPress/gatherpress/pull/552)
- French translation context for "not attending". [#541](https://github.com/GatherPress/gatherpress/pull/541) [#546](https://github.com/GatherPress/gatherpress/pull/546)
- Add Carsten Bach to project credits. [#553](https://github.com/GatherPress/gatherpress/pull/553)
- Remove leading underscores from event and venue meta keys (visibility cleanup). [#554](https://github.com/GatherPress/gatherpress/pull/554)

### Fixed
- Fix RSVP modal when the user is not logged in. [#533](https://github.com/GatherPress/gatherpress/pull/533)
- Make sure `textdomain` is loaded before registering post types. [#584](https://github.com/GatherPress/gatherpress/pull/584)

### Removed
- Revert PR #507's `get_formatted_datetime` optimization due to caching interaction. [#516](https://github.com/GatherPress/gatherpress/pull/516)

## [0.27.0] - 2024-01-22

Initial public release. Represents 18+ months of pre-1.0 development and ships the core feature set:

### Added
- Custom post types `gatherpress_event` and `gatherpress_venue` with REST API support.
- Event datetime handling with timezone support and a dedicated `gatherpress_events` database table.
- RSVP system with attending / not-attending / waiting-list states, max-attendance limits, and an attendees list.
- Venue management with name, address, phone, website, and embedded map.
- Online-event link handling (gated on RSVP state).
- "Add to calendar" links covering Google, iCal, Outlook, and Yahoo.
- Email functionality for site organizers to message attendees.
- Block library: Event Date, Venue, Online Event, Add to Calendar, Event List, Attendance Selector, Attendees, Modal, Dropdown.
- Event archive pages for upcoming and past events with configurable archive page assignments.
- Topic taxonomy for events.
- Settings UI under the Events admin menu, including roles and credits.
- WP-CLI integration.
- PHP 8.0 / 8.1 / 8.2 / 8.3 compatibility matrix.
- Initial unit test suite with code coverage via SonarCloud.
- Multilingual screenshots and i18n scaffolding.

[0.34.1]: https://github.com/GatherPress/gatherpress/compare/0.34.0...0.34.1
[0.34.0]: https://github.com/GatherPress/gatherpress/compare/0.33.3...0.34.0
[0.33.3]: https://github.com/GatherPress/gatherpress/compare/0.33.2...0.33.3
[0.33.2]: https://github.com/GatherPress/gatherpress/compare/0.33.1...0.33.2
[0.33.1]: https://github.com/GatherPress/gatherpress/compare/0.33.0...0.33.1
[0.33.0]: https://github.com/GatherPress/gatherpress/compare/0.32.3...0.33.0
[0.32.3]: https://github.com/GatherPress/gatherpress/compare/0.32.2...0.32.3
[0.32.2]: https://github.com/GatherPress/gatherpress/compare/0.32.1...0.32.2
[0.32.1]: https://github.com/GatherPress/gatherpress/compare/0.32.0...0.32.1
[0.32.0]: https://github.com/GatherPress/gatherpress/compare/0.31.0...0.32.0
[0.31.0]: https://github.com/GatherPress/gatherpress/compare/0.30.0...0.31.0
[0.30.0]: https://github.com/GatherPress/gatherpress/compare/0.29.3...0.30.0
[0.29.3]: https://github.com/GatherPress/gatherpress/compare/0.29.2...0.29.3
[0.29.2]: https://github.com/GatherPress/gatherpress/compare/0.29.1...0.29.2
[0.29.1]: https://github.com/GatherPress/gatherpress/compare/0.29.0...0.29.1
[0.29.0]: https://github.com/GatherPress/gatherpress/compare/0.28.0...0.29.0
[0.28.0]: https://github.com/GatherPress/gatherpress/compare/0.27.0...0.28.0
[0.27.0]: https://github.com/GatherPress/gatherpress/releases/tag/0.27.0
