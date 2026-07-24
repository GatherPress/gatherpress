# gatherpress_log_geocoding_errors


Filters whether to write a PHP error-log line when Photon returns a body
that can't be JSON-decoded.

Defaults to `WP_DEBUG` so production sites stay quiet, but can be
force-enabled (e.g. for tests, or in staging) via:

add_filter( 'gatherpress_log_geocoding_errors', '__return_true' );

## Auto-generated Example

```php
add_filter(
   'gatherpress_log_geocoding_errors',
    function( bool $should_log ) {
        // Your code here.
        return $should_log;
    }
);
```

## Parameters

- *`bool`* `$should_log` Default: value of WP_DEBUG.

## Files

- [includes/core/classes/class-geocoding.php:1201](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L1201)
```php
apply_filters(
			'gatherpress_log_geocoding_errors',
			defined( 'WP_DEBUG' ) && WP_DEBUG
		)
```



[← All Hooks](Hooks.md)
