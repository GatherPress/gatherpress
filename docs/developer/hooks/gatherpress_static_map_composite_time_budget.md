# gatherpress_static_map_composite_time_budget


Filter the wall-clock budget (in seconds) for a single OSM
render() call. When the deadline is exceeded mid-loop, remaining
tiles are skipped and the gray background shows through.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_composite_time_budget',
    function( GatherPress\float $budget ) {
        // Your code here.
        return $budget;
    }
);
```

## Parameters

- *`GatherPress\float`* `$budget` Default budget from COMPOSITE_TIME_BUDGET.

## Files

- [includes/core/classes/venue/map/provider/class-osm.php:170](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/provider/class-osm.php#L170)
```php
apply_filters(
			'gatherpress_static_map_composite_time_budget',
			self::COMPOSITE_TIME_BUDGET
		)
```



[← All Hooks](Hooks.md)
