# gatherpress_calendar_time_slot_duration


Filters the hours in each labeled row of the time grid, to make a long day take less height.

Defaults to 1. Each row has four steps. An event starts at the step at or before its start
time and ends at the step at or after its end time: 15-minute steps for 1-hour rows, 30-minute
steps for 2-hour rows. Values below 1 become 1.

## Auto-generated Example

```php
add_filter(
   'gatherpress_calendar_time_slot_duration',
    function( int $slot_hours ) {
        // Your code here.
        return $slot_hours;
    }
);
```

## Parameters

- *`int`* `$slot_hours` Hours per slot.

## Returns

`int` Hours per slot.

## Files

- [includes/core/classes/calendar/class-time-grid.php:147](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/calendar/class-time-grid.php#L147)
```php
apply_filters( 'gatherpress_calendar_time_slot_duration', 1 )
```



[← All Hooks](Hooks.md)
