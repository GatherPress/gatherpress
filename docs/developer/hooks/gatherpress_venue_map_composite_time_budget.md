# gatherpress_venue_map_composite_time_budget


Filter the wall-clock budget (in seconds) for a single
composite_image() call. When the deadline is exceeded mid-loop,
remaining tiles are skipped and the gray background shows
through. Accepts int or float.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_composite_time_budget',
    function( GatherPress\float $budget ) {
        // Your code here.
        return $budget;
    }
);
```

## Parameters

- *`GatherPress\float`* `$budget` Default budget from COMPOSITE_TIME_BUDGET.

## Files

- [includes/core/classes/class-venue-map.php:1514](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map.php#L1514)
```php
apply_filters(
			'gatherpress_venue_map_composite_time_budget',
			self::COMPOSITE_TIME_BUDGET
		)
```



[← All Hooks](Hooks.md)
