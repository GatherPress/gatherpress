
## class-admin-list.php

- [`gatherpress_event_datetime_label`](gatherpress_event_datetime_label.md) Filters the label used for the event-date admin list column.

## class-assets.php

- [`gatherpress_asset_critical`](gatherpress_asset_critical.md) Filters whether an asset file is considered critical.
- [`gatherpress_asset_utility_style_block_prefixes`](gatherpress_asset_utility_style_block_prefixes.md) Filters additional block-name prefixes whose blocks should

## class-autoloader.php

- [`gatherpress_autoloader`](gatherpress_autoloader.md) Filters the registered autoloaders for GatherPress.

## class-calendar.php

- [`gatherpress_calendar_url`](gatherpress_calendar_url.md) Filters the calendar URL for a single event.

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

- [`gatherpress_async_geocode_delay`](gatherpress_async_geocode_delay.md) Filters the delay between an address-change save and the cron firing.
- [`gatherpress_async_geocode_failed`](gatherpress_async_geocode_failed.md) Fires when the async geocode handler exits because Photon
- [`gatherpress_async_geocode_pre_enqueue_job`](gatherpress_async_geocode_pre_enqueue_job.md) Filter the geocode enqueue call to take over scheduling.
- [`gatherpress_formatted_address`](gatherpress_formatted_address.md) Filters the one-line address label minted from a geocoder result.
- [`gatherpress_geocode_on_save_enabled`](gatherpress_geocode_on_save_enabled.md) Filters whether the async geocode should run on venue save.
- [`gatherpress_geocode_rate_limit_enabled`](gatherpress_geocode_rate_limit_enabled.md) Filter whether the geocode REST rate limit is enforced.
- [`gatherpress_geocode_rate_limit_per_minute`](gatherpress_geocode_rate_limit_per_minute.md) Filter the per-user requests-per-minute ceiling for the
- [`gatherpress_log_geocoding_errors`](gatherpress_log_geocoding_errors.md) Filters whether to write a PHP error-log line when Photon returns a body
- [`gatherpress_photon_api_url`](gatherpress_photon_api_url.md) Filters the Photon API base URL used for geocoding and address search.

## class-import.php

- [`gatherpress_import`](gatherpress_import.md) Fires for every GatherPress data to be imported.

## class-manager.php

- [`gatherpress_register_static_map_providers`](gatherpress_register_static_map_providers.md) Fires when venue map providers are being registered.

## class-map.php

- [`gatherpress_map_height`](gatherpress_map_height.md) Filter the height used when rendering the static venue map.
- [`gatherpress_map_zoom`](gatherpress_map_zoom.md) Filter the zoom level used when rendering the static venue map.
- [`gatherpress_static_map_descriptors`](gatherpress_static_map_descriptors.md) Filters the parsed descriptor map for a venue.
- [`gatherpress_static_map_generate_2x`](gatherpress_static_map_generate_2x.md) Filter whether to generate the retina (2×) static-map variant.

## class-migrate.php

- [`gatherpress_pseudo_post_metas`](gatherpress_pseudo_post_metas.md) Filters the list of data-names and their respective export- and import-callbacks.

## class-osm.php

- [`gatherpress_static_map_composite_time_budget`](gatherpress_static_map_composite_time_budget.md) Filter the wall-clock budget (in seconds) for a single OSM
- [`gatherpress_static_map_tile_url`](gatherpress_static_map_tile_url.md) Filter the tile URL template used by the OSM static map provider.

## class-prewarm.php

- [`gatherpress_static_map_prewarm_batch_size`](gatherpress_static_map_prewarm_batch_size.md) Filter the venue-map prewarm scan batch size.
- [`gatherpress_static_map_prewarm_content_batch_size`](gatherpress_static_map_prewarm_content_batch_size.md) Filter the venue-map prewarm content-scan batch size.
- [`gatherpress_static_map_prewarm_pre_enqueue_job`](gatherpress_static_map_prewarm_pre_enqueue_job.md) Filter the prewarm enqueue call to take over scheduling.

## class-query.php

- [`gatherpress_rsvp_comment_query_exclusion`](gatherpress_rsvp_comment_query_exclusion.md) Filters whether RSVP comments should be excluded from a comment query.

## class-roles.php

- [`gatherpress_roles`](gatherpress_roles.md) Filter the list of roles for GatherPress.

## class-settings.php

- [`gatherpress_interactive_map_tile_attribution`](gatherpress_interactive_map_tile_attribution.md) Filters the attribution HTML rendered with the venue map.
- [`gatherpress_interactive_map_tile_url`](gatherpress_interactive_map_tile_url.md) Filters the Leaflet tile layer URL used by the venue map.
- [`gatherpress_network_is_option_inherited`](gatherpress_network_is_option_inherited.md) Filters whether a specific GatherPress option is inherited from the network.
- [`gatherpress_sub_pages`](gatherpress_sub_pages.md) Filters the list of GatherPress sub pages.

## class-setup.php

- [`gatherpress_event_archive_mode`](gatherpress_event_archive_mode.md) Filters the resolved event archive mode.
- [`gatherpress_event_starter_patterns`](gatherpress_event_starter_patterns.md) Filters the array of event starter pattern definitions.
- [`gatherpress_is_alpha_active`](gatherpress_is_alpha_active.md) Filters whether GatherPress Alpha is considered active.
- [`gatherpress_venue_post_type`](gatherpress_venue_post_type.md) Filters the post type used as the venue.
- [`gatherpress_venue_starter_patterns`](gatherpress_venue_starter_patterns.md) Filters the array of venue starter pattern definitions.
- [`post_type_labels_%s`](post_type_labels_%s.md)

## class-shadow-source.php

- [`gatherpress_shadow_taxonomy_args`](gatherpress_shadow_taxonomy_args.md) Filters the taxonomy registration args for a shadow-source post type.
- [`gatherpress_shadow_taxonomy_object_types`](gatherpress_shadow_taxonomy_object_types.md) Filters which event post types the shadow taxonomy should be

## class-topic.php

- [`taxonomy_labels_%s`](taxonomy_labels_%s.md)

## class-user.php

- [`gatherpress_event_updates_default_opt_in`](gatherpress_event_updates_default_opt_in.md) Filters the default state of the event updates opt-in.

## class-utility.php

- [`gatherpress_pre_get_http_input`](gatherpress_pre_get_http_input.md) Short-circuit filter for HTTP input retrieval during testing.
- [`gatherpress_pre_get_wp_referer`](gatherpress_pre_get_wp_referer.md) Short-circuit filter for wp_get_referer() during testing.
- [`gatherpress_template_path`](gatherpress_template_path.md) Filters the resolved template path returned by `Utility::locate_template()`.

## network-page.php

- [`gatherpress_settings_section`](gatherpress_settings_section.md) Fires so tabs that render via the GatherPress settings section action
