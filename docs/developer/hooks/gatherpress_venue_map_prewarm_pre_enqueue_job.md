# gatherpress_venue_map_prewarm_pre_enqueue_job


Filter the prewarm enqueue call to take over scheduling.

Return any non-null value from this filter to suppress both
the WP-Cron dedup check below and the `wp_schedule_single_event()`
call — a companion plugin that hooks this filter owns the
full scheduling path end-to-end (including its own dedup,
since the fanout by-passes `wp_next_scheduled()`). Mirrors
the core `pre_*` filter convention: `null` means "pass
through to the default"; everything else, including falsy
values like `false`, `0`, and `''`, short-circuits.

Core ignores the return value past the null check, so a
callback is free to return whatever is useful to itself —
the established convention is a scheduler-specific
identifier (e.g. the Action Scheduler action ID returned by
`as_enqueue_async_action()`) so other filters / debug
tooling downstream can correlate the job.


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

- [includes/core/classes/venue/class-map-prewarm.php:373](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-map-prewarm.php#L373)
```php
apply_filters(
			'gatherpress_venue_map_prewarm_pre_enqueue_job',
			null,
			self::CRON_ACTION,
			$args
		)
```



[← All Hooks](Hooks.md)
