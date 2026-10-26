# gatherpress_calendar_weekend_days


Filters which days of the week are weekend days, for a site in a region with a different weekend.

Defaults to Sunday (0) and Saturday (6). Weekend days get the `is-weekend` class, and the
calendar hides them when Show Weekends is off.

Across different cultures, religions, and regions, the weekend days vary significantly:
- Western standard: Saturday (6) and Sunday (0).
- Middle East & North Africa (e.g., Egypt, Saudi Arabia, Jordan): Friday (5) and Saturday (6).
- Israel: Friday (5) and Saturday (6) (observing Shabbat).
- Iran: Friday (5) as the sole official weekend day.
- Nepal: Saturday (6) as the sole official weekend day.

## Auto-generated Example

```php
add_filter(
   'gatherpress_calendar_weekend_days',
    function( GatherPress\int[] $weekend_days ) {
        // Your code here.
        return $weekend_days;
    }
);
```

## Parameters

- *`GatherPress\int[]`* `$weekend_days` Array of day numbers where 0 = Sunday, 1 = Monday, ..., 6 = Saturday.

## Returns

`GatherPress\int[]` Day numbers. Values that are not 0 to 6, and repeated days, are dropped.

## Files

- [includes/core/classes/calendar/class-week.php:123](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/calendar/class-week.php#L123)
```php
apply_filters( 'gatherpress_calendar_weekend_days', array( 0, 6 ) )
```



[← All Hooks](Hooks.md)
