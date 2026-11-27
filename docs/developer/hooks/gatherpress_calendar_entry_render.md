# gatherpress_calendar_entry_render


Fires before the calendar renders the entry template of one event.

The calendar has added its filters for this event. Callbacks can
add their own filters, or remove one of the calendar's, for example:
remove_filter( 'the_title', array( Calendar_Entries::get_instance(), 'filter_empty_title' ), 10 );
The calendar removes its own filters after the entry renders. For a
calendar inside an entry template, that is after the outer entry. The
action fires once for each entry, so a multi-day event fires it once
for each of its visible days.

## Auto-generated Example

```php
add_action(
   'gatherpress_calendar_entry_render',
    function(
        int $post_id,
        string $post_type,
        array<int, array<string,,
        array<string, mixed>
    ) {
        // Your code here.
    },
    10,
    4
);
```

## Parameters

- *`int`* `$post_id` Event post ID.
- *`string`* `$post_type` Event post type.
- *`array<int,`* `array<string,` mixed>> $inner_blocks Parsed blocks of the entry template.
- *`array<string,`* `mixed>` $span_data    Multi-day span descriptors.

## Files

- [includes/core/classes/blocks/class-calendar-entries.php:572](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/class-calendar-entries.php#L572)
```php
do_action( 'gatherpress_calendar_entry_render', $post_id, $post_type, $inner_blocks, $span_data )
```



[← All Hooks](Hooks.md)
