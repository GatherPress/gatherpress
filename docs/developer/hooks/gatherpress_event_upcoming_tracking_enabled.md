# gatherpress_event_upcoming_tracking_enabled


Filters whether the upcoming event IDs of every post type with event dates are tracked.

Tracked IDs are stored in the `gatherpress_event_upcoming_ids` option as
`array<string, int[]>`, keyed by post type, and checked by a daily sweep
that fires `gatherpress_event_ended` for any end that WP-Cron missed.
Read them with `Scheduler::get_tracked_ids()`. To track a single post type,
use `gatherpress_event_upcoming_tracking_enabled_{$post_type}` instead.

## Example

```php
add_filter( 'gatherpress_event_upcoming_tracking_enabled', '__return_true' );

// Query the upcoming events without a meta_query:
$upcoming_ids = \GatherPress\Core\Event\Scheduler::get_instance()->get_tracked_ids( 'gatherpress_event' );

if ( ! empty( $upcoming_ids ) ) {
    $query = new WP_Query(
        array(
            'post_type' => 'gatherpress_event',
            'post__in'  => $upcoming_ids,
        )
    );
}
```

## Parameters

- *`bool`* `$enabled` Whether tracking is enabled. Default false.

## Files

- [includes/core/classes/event/class-scheduler.php:375](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-scheduler.php#L375)
```php
apply_filters( 'gatherpress_event_upcoming_tracking_enabled', false )
```



[← All Hooks](Hooks.md)
