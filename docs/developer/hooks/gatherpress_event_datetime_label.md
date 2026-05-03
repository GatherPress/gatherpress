# gatherpress_event_datetime_label


Filters the label used for the event-date admin list column.

Lets post types that declare `gatherpress-event-date` support relabel the
column without having to drop and re-add it via WordPress core's
`manage_{$post_type}_posts_columns` filter. A `production` post type can
surface the column as "Premiere date", a `release` post type as "Release
date", etc., while keeping the underlying `datetime` column key (and its
sortable behavior) unchanged.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_datetime_label',
    function(
        string $label,
        string $post_type
    ) {
        // Your code here.
        return $label;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$label` Default column label.
- *`string`* `$post_type` Post type the admin list is currently rendering.

## Files

- [includes/core/classes/event/class-admin-list.php:630](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-admin-list.php#L630)
```php
apply_filters(
				'gatherpress_event_datetime_label',
				__( 'Event date &amp; time', 'gatherpress' ),
				$post_type
			)
```



[← All Hooks](Hooks.md)
