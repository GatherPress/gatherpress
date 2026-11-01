# gatherpress_event_reminder_timings


Filters the timings an event reminder can go out at.

Lets a site add a timing, such as a week before, or remove one. Each
timing gets its own checkbox under Settings > RSVP > Notifications.
The sweep runs hourly, so a reminder goes out within an hour after
its time.


checkbox label, seconds before the start, and whether it is checked by default, keyed by name.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_reminder_timings',
    function( array array<string, ) {
        // Your code here.
        return array<string,;
    }
);
```

## Parameters

- *`array`* `array<string,` array{label: string, seconds: int, default: bool}> $timings Each timing's Other variable names: `$string_list`

## Returns

`array<string,` array{label: string, seconds: int, default: bool}> The timings.

## Files

- [includes/core/classes/event/class-reminder.php:122](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-reminder.php#L122)
```php
apply_filters(
			'gatherpress_event_reminder_timings',
			array(
				'day_before'  => array(
					'label'   => __( 'A day before', 'gatherpress' ),
					'seconds' => DAY_IN_SECONDS,
					'default' => true,
				),
				'hour_before' => array(
					'label'   => __( 'An hour before', 'gatherpress' ),
					'seconds' => HOUR_IN_SECONDS,
					'default' => false,
				),
			)
		)
```



[← All Hooks](Hooks.md)
