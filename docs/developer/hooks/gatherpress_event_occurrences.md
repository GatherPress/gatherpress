# gatherpress_event_occurrences


Lets repeat rules supply an event's dates within a range.

Calendars, lists and feeds ask for dates through here, so a plugin that
expands repeat rules returns each date in the range as an occurrence.


if it overlaps the range.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_occurrences',
    function(
        Occurrence[],
        int $post_id,
        GatherPress\DateTimeInterface $from,
        GatherPress\DateTimeInterface $to
    ) {
        // Your code here.
        return Occurrence[];
    },
    10,
    4
);
```

## Parameters

- `Occurrence[]` $occurrences The occurrences. Without repeat rules, the event's one date Other variable names: `$occurrences`
- *`int`* `$post_id` The event post ID.
- *`GatherPress\DateTimeInterface`* `$from` Start of the range.
- *`GatherPress\DateTimeInterface`* `$to` End of the range, exclusive.

## Returns

`GatherPress\Occurrence[]` The occurrences that overlap the range.

## Files

- [includes/core/classes/event/class-occurrences.php:121](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-occurrences.php#L121)
```php
apply_filters( 'gatherpress_event_occurrences', $occurrences, $this->post_id(), $from, $to )
```



[← All Hooks](Hooks.md)
