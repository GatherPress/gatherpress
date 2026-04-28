# gatherpress_geocode_rate_limit_enabled


Filter whether the geocode REST rate limit is enforced.

Returning `false` disables the rate limit entirely — no
per-user bucket is read or written, no 429 is ever returned.
Useful for sites running their own upstream rate limiting at
a CDN / WAF layer that already covers this surface, or for
automated test environments that want to bypass the throttle.

Mirrors the shape of `gatherpress_geocode_on_save_enabled`
(cron side) for consistency: same filter pattern across
both Photon-traffic toggles.

## Auto-generated Example

```php
add_filter(
   'gatherpress_geocode_rate_limit_enabled',
    function( bool $enabled ) {
        // Your code here.
        return $enabled;
    }
);
```

## Parameters

- *`bool`* `$enabled` Whether the rate limit is enforced. Default true.

## Files

- [includes/core/classes/class-geocoding.php:500](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L500)
```php
apply_filters( 'gatherpress_geocode_rate_limit_enabled', true )
```



[← All Hooks](Hooks.md)
