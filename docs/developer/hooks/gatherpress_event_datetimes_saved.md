# gatherpress_event_datetimes_saved


Fires after an event's datetimes are saved.

## Auto-generated Example

```php
add_action(
   'gatherpress_event_datetimes_saved',
    function(
        int $post_id,
        array<string, string>,
        array<string, string>|null
    ) {
        // Your code here.
    },
    10,
    3
);
```

## Parameters

- *`int`* `$post_id` Event post ID.
- *`array<string,`* `string>` $saved    The saved datetimes, both local and GMT, and the timezone.
- *`array<string,`* `string>|null` $previous The datetimes before this save, or null for the first save.

## Files

- [includes/core/classes/event/class-event.php:1270](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L1270)
```php
do_action(
				'gatherpress_event_datetimes_saved',
				(int) $fields['post_id'],
				array_map( 'strval', array_diff_key( $fields, array( 'post_id' => 0 ) ) ),
				$previous
			)
```



[← All Hooks](Hooks.md)
