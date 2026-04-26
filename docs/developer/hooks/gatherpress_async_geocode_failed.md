# gatherpress_async_geocode_failed


Fires when the async geocode handler exits because Photon
returned a `WP_Error`. Observability plugins can hook this to
surface chronic failures (DNS issues, rate-limit responses,
Photon outages) without parsing the WP-Cron error log.

## Auto-generated Example

```php
add_action(
   'gatherpress_async_geocode_failed',
    function(
        int $post_id,
        WP_Error $result
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$post_id` Venue post ID whose geocode failed.
- *`WP_Error`* `$result` The error returned by `geocode_to_result()`.

## Files

- [includes/core/classes/class-geocoding.php:337](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L337)
```php
do_action( 'gatherpress_async_geocode_failed', $post_id, $result )
```



[← All Hooks](Hooks.md)
