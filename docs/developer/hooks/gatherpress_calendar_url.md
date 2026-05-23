# gatherpress_calendar_url


Filters the calendar URL for a single event.

Lets integrators rewrite the calendar URL (iCal / Outlook download,
Google / Yahoo redirect) for an event before it reaches the front
end — useful for routing calendar downloads through a CDN, swapping
the host for a federation-friendly canonical, or appending tracking
params.

## Auto-generated Example

```php
add_filter(
   'gatherpress_calendar_url',
    function(
        string $endpoint_url,
        WP_Post $post
    ) {
        // Your code here.
        return $endpoint_url;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$endpoint_url` The full calendar URL.
- *`WP_Post`* `$post` The corresponding event post.

## Returns

`string` The filtered calendar URL.

## Files

- [includes/core/classes/calendar/class-calendar.php:328](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/calendar/class-calendar.php#L328)
```php
apply_filters(
				'gatherpress_calendar_url',
				$endpoint_url,
				$post
			)
```



[← All Hooks](Hooks.md)
