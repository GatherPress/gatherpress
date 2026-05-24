# gatherpress_geocode_rate_limit_per_minute


Filter the per-user requests-per-minute ceiling for the
geocode REST endpoints (`/geocode` and `/geocode/search`).

Both endpoints share one fixed-window per-user bucket. Once
this ceiling is reached within a 60-second window, additional
requests for the same user return HTTP `429 Too Many Requests`
with a `Retry-After` header pointing at the remaining seconds
in the window. Lower this value to be stricter with abusive
clients; raise it for sites with debounced-but-eager
autocomplete UIs or bulk-import workflows.

Values below `1` are clamped to `1` (a zero ceiling would 429
every request, including the first).

## Auto-generated Example

```php
add_filter(
   'gatherpress_geocode_rate_limit_per_minute',
    function( int $ceiling ) {
        // Your code here.
        return $ceiling;
    }
);
```

## Parameters

- *`int`* `$ceiling` Default per-user requests-per-minute ceiling.

## Files

- [includes/core/classes/class-geocoding.php:536](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L536)
```php
apply_filters(
			'gatherpress_geocode_rate_limit_per_minute',
			self::GEOCODE_RATE_LIMIT_DEFAULT_PER_MINUTE
		)
```



[← All Hooks](Hooks.md)
