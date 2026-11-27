# gatherpress_event_end_cache_keys


Filters cache keys to invalidate when an event ends.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_end_cache_keys',
    function(
        array<string>|mixed $cache_keys,
        int $post_id
    ) {
        // Your code here.
        return $cache_keys;
    },
    10,
    2
);
```

## Parameters

- *`array<string>|mixed`* `$cache_keys` Array of cache keys to invalidate.
- *`int`* `$post_id` The event post ID.

## Files

- [includes/core/classes/event/class-scheduler.php:423](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-scheduler.php#L423)
```php
apply_filters(
			'gatherpress_event_end_cache_keys',
			$default_keys,
			$post_id
		)
```



[← All Hooks](Hooks.md)
