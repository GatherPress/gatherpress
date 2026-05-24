# gatherpress_event_archive_mode


Filters the resolved event archive mode.

Lets plugins pin a post type's archive to `upcoming`, `past`,
or `none` programmatically — including overriding the Event
Archive setting for `gatherpress_event`. Returned values
outside the valid set are coerced to `upcoming`.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_archive_mode',
    function(
        string $mode,
        string $post_type
    ) {
        // Your code here.
        return $mode;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$mode` Current archive mode (`upcoming`, `past`, or `none`).
- *`string`* `$post_type` Post type being archived.

## Files

- [includes/core/classes/event/class-setup.php:517](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L517)
```php
apply_filters( 'gatherpress_event_archive_mode', $mode, $post_type )
```



[← All Hooks](Hooks.md)
