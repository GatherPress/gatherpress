# gatherpress_geocode_on_save_enabled


Filters whether the async geocode should run on venue save.

Hosts that need to control egress (firewalled corp installs,
privacy-sensitive setups, dev environments without Photon access)
can return false here to suppress the cron. Structured-address
fields then stay at their last persisted values until the filter
is re-enabled or `update_post_meta` is called directly from
trusted code.

## Auto-generated Example

```php
add_filter(
   'gatherpress_geocode_on_save_enabled',
    function(
        bool $enabled,
        int $post_id
    ) {
        // Your code here.
        return $enabled;
    },
    10,
    2
);
```

## Parameters

- *`bool`* `$enabled` True to schedule the geocode, false to skip.
- *`int`* `$post_id` Venue post ID.

## Files

- [includes/core/classes/class-geocoding.php:201](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L201)
```php
apply_filters( 'gatherpress_geocode_on_save_enabled', true, $post_id )
```



[← All Hooks](Hooks.md)
