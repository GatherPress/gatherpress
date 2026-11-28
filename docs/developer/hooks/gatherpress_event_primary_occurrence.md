# gatherpress_event_primary_occurrence


Lets repeat rules pick a series' primary occurrence.

The event page and lists show this date for a series, so a plugin that
expands repeat rules returns the next date that has not ended.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_primary_occurrence',
    function(
        Occurrence|null,
        int $post_id
    ) {
        // Your code here.
        return Occurrence|null;
    },
    10,
    2
);
```

## Parameters

- `Occurrence|null` $occurrence The occurrence. Without repeat rules, the event's one date. Other variable names: `$occurrence`
- *`int`* `$post_id` The event post ID.

## Returns

`GatherPress\Occurrence|null` The primary occurrence, or null if the event has none.

## Files

- [includes/core/classes/event/class-occurrences.php:197](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-occurrences.php#L197)
```php
apply_filters( 'gatherpress_event_primary_occurrence', $occurrence, $this->post_id() )
```



[← All Hooks](Hooks.md)
