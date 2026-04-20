
## class-assets.php

- [`gatherpress_asset_critical`](gatherpress_asset_critical.md) Filters whether an asset file is considered critical.

## class-autoloader.php

- [`gatherpress_autoloader`](gatherpress_autoloader.md) Filters the registered autoloaders for GatherPress.

## class-event-query.php

- [`gatherpress_query_vars`](gatherpress_query_vars.md) This filter is documented in includes/query-loop.php

## class-event.php

- [`gatherpress_date_format`](gatherpress_date_format.md)
- [`gatherpress_datetime_format`](gatherpress_datetime_format.md)
- [`gatherpress_force_online_event_link`](gatherpress_force_online_event_link.md) Filters whether to force the display of the online event link.
- [`gatherpress_time_format`](gatherpress_time_format.md)
- [`gatherpress_timezone`](gatherpress_timezone.md)

## class-feed.php

- [`gatherpress_event_feed_content`](gatherpress_event_feed_content.md) Filters the event content in feeds.
- [`gatherpress_event_feed_excerpt`](gatherpress_event_feed_excerpt.md) Filters the event excerpt in feeds.

## class-geocoding.php

- [`gatherpress_log_geocoding_errors`](gatherpress_log_geocoding_errors.md) Filters whether to write a PHP error-log line when Photon returns a body
- [`gatherpress_photon_api_url`](gatherpress_photon_api_url.md) Filters the Photon API base URL used for geocoding and address search.

## class-import.php

- [`gatherpress_import`](gatherpress_import.md) Fires for every GatherPress data to be imported.

## class-migrate.php

- [`gatherpress_pseudopostmetas`](gatherpress_pseudopostmetas.md) Filters the list of data-names and their respective export- and import-callbacks.

## class-roles.php

- [`gatherpress_roles`](gatherpress_roles.md) Filter the list of roles for GatherPress.

## class-settings.php

- [`gatherpress_map_tile_attribution`](gatherpress_map_tile_attribution.md) Filters the attribution HTML rendered with the venue map.
- [`gatherpress_map_tile_url`](gatherpress_map_tile_url.md) Filters the Leaflet tile layer URL used by the venue map.
- [`gatherpress_network_is_option_inherited`](gatherpress_network_is_option_inherited.md) Filters whether a specific GatherPress option is inherited from the network.
- [`gatherpress_sub_pages`](gatherpress_sub_pages.md) Filters the list of GatherPress sub pages.

## class-setup.php

- [`gatherpress_is_alpha_active`](gatherpress_is_alpha_active.md) Filters whether GatherPress Alpha is considered active.

## class-user.php

- [`gatherpress_event_updates_default_opt_in`](gatherpress_event_updates_default_opt_in.md) Filters the default state of the event updates opt-in.

## class-utility.php

- [`gatherpress_pre_get_http_input`](gatherpress_pre_get_http_input.md) Short-circuit filter for HTTP input retrieval during testing.
- [`gatherpress_pre_get_wp_referer`](gatherpress_pre_get_wp_referer.md) Short-circuit filter for wp_get_referer() during testing.

## class-venue-map-prewarm.php

- [`gatherpress_venue_map_prewarm_batch_size`](gatherpress_venue_map_prewarm_batch_size.md) Filter the venue-map prewarm scan batch size.
- [`gatherpress_venue_map_prewarm_content_batch_size`](gatherpress_venue_map_prewarm_content_batch_size.md) Filter the venue-map prewarm content-scan batch size.
- [`gatherpress_venue_map_prewarm_pre_enqueue_job`](gatherpress_venue_map_prewarm_pre_enqueue_job.md) Filter the prewarm enqueue call to take over scheduling.

## class-venue-map.php

- [`gatherpress_venue_map_composite_time_budget`](gatherpress_venue_map_composite_time_budget.md) Filter the wall-clock budget (in seconds) for a single
- [`gatherpress_venue_map_descriptors`](gatherpress_venue_map_descriptors.md) Filters the parsed descriptor map for a venue.
- [`gatherpress_venue_map_generate_2x`](gatherpress_venue_map_generate_2x.md) Filter whether to generate the retina (2×) static-map variant.
- [`gatherpress_venue_map_height`](gatherpress_venue_map_height.md) Filter the height used when rendering the static venue map.
- [`gatherpress_venue_map_tile_url`](gatherpress_venue_map_tile_url.md) Filter the tile URL template used by the static venue map.
- [`gatherpress_venue_map_zoom`](gatherpress_venue_map_zoom.md) Filter the zoom level used when rendering the static venue map.

## class-venue-setup.php

- [`gatherpress_venue_post_type`](gatherpress_venue_post_type.md) Filters the post type used as the venue.

## network-page.php

- [`gatherpress_settings_section`](gatherpress_settings_section.md) Fires so tabs that render via the GatherPress settings section action
