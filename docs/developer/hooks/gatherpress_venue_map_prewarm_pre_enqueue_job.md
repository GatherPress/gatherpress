# gatherpress_venue_map_prewarm_pre_enqueue_job


Filter the prewarm enqueue call to take over scheduling.

Return any non-null value from this filter to suppress the
default WP-Cron path so a companion plugin can enqueue the
same work through Action Scheduler or another persistent
queue. The returned value is not used by core — it's only
inspected for `null` vs. non-null — so a callback can return
anything meaningful to itself (e.g. an AS action ID).

Mirrors the core `pre_*` filter convention (`pre_update_option`,
`pre_set_site_transient`, etc.).


`array( $venue_post_id, $zoom, $width, $height, $aspect_ratio )`.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_prewarm_pre_enqueue_job',
    function(
        mixed $short_circuit,
        string $hook,
        array $args
    ) {
        // Your code here.
        return $short_circuit;
    },
    10,
    3
);
```

## Parameters

- *`mixed`* `$short_circuit` Non-null to suppress the default enqueue.
- *`string`* `$hook` Action hook name fired when the job runs.
- *`array`* `$args` Args passed to the action hook when the job runs:

## Files

- [includes/core/classes/class-venue-map-prewarm.php:367](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map-prewarm.php#L367)
```php
apply_filters(
			'gatherpress_venue_map_prewarm_pre_enqueue_job',
			null,
			self::CRON_ACTION,
			$args
		)
```



[← All Hooks](Hooks.md)
