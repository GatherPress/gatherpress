# gatherpress_async_geocode_delay


Filters the delay between an address-change save and the cron firing.

Default 5 seconds is short enough to feel near-realtime and long
enough that the originating save has fully committed. Sites with
heavy save hooks (revisions fanning out, multilingual sync, etc.)
may need longer; sites that batch saves can pass a larger value to
coalesce. Returning 0 fires effectively immediately.

## Auto-generated Example

```php
add_filter(
   'gatherpress_async_geocode_delay',
    function(
        int $delay,
        int $post_id
    ) {
        // Your code here.
        return $delay;
    },
    10,
    2
);
```

## Parameters

- *`int`* `$delay` Delay in seconds. Default 5.
- *`int`* `$post_id` Venue post ID.

## Files

- [includes/core/classes/class-geocoding.php:293](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L293)
```php
apply_filters( 'gatherpress_async_geocode_delay', self::CRON_DELAY_SECONDS, $post_id )
```



[← All Hooks](Hooks.md)
