# gatherpress_calendar_max_age


Filters how long calendar responses may be reused by clients and caches.

Applies to the `Cache-Control` header on ICS responses and to how long
a rendered body is kept server-side, so the two cannot drift.
Return 0 to send `no-cache` and rebuild on every request.

## Auto-generated Example

```php
add_filter(
   'gatherpress_calendar_max_age',
    function( int $max_age ) {
        // Your code here.
        return $max_age;
    }
);
```

## Parameters

- *`int`* `$max_age` Seconds a calendar response stays fresh.

## Returns

`int` Seconds a calendar response stays fresh.

## Files

- [includes/core/classes/calendar/class-cache.php:136](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/calendar/class-cache.php#L136)
```php
apply_filters( 'gatherpress_calendar_max_age', self::DEFAULT_MAX_AGE )
```



[← All Hooks](Hooks.md)
