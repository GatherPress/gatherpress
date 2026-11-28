# gatherpress_event_occurrence_by_key


Lets repeat rules supply the occurrence for a key.

Per-date pages and RSVPs look a date up by its key, so a plugin that
expands repeat rules returns the occurrence that key names.


if its key matches.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_occurrence_by_key',
    function(
        Occurrence|null,
        int $post_id,
        string
    ) {
        // Your code here.
        return Occurrence|null;
    },
    10,
    3
);
```

## Parameters

- `Occurrence|null` $occurrence    The occurrence. Without repeat rules, the event's one date Other variable names: `$occurrence`
- *`int`* `$post_id` The event post ID.
- `string` $recurrence_id The occurrence's key. Other variable names: `$recurrence_id`

## Returns

`GatherPress\Occurrence|null` The occurrence, or null if the event has none with that key.

## Files

- [includes/core/classes/event/class-occurrences.php:161](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-occurrences.php#L161)
```php
apply_filters(
			'gatherpress_event_occurrence_by_key',
			$occurrence,
			$this->post_id(),
			$recurrence_id
		)
```



[← All Hooks](Hooks.md)
