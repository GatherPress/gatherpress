# gatherpress_event_statuses


Filters the operational statuses an event can be in.

Each status is keyed by the slug stored against the event. An entry
holds the `label` and `description` people read, the `priority` used
to resolve precedence among multiple statuses, and the `schema` (Schema.org EventStatusType) and `ical`
(RFC 5545 STATUS) values published for it. Set `strike_date` to true
to cross out the event's date while it is in the status. Anything omitted falls
back to something that says the event is going ahead, so a status
never tells a calendar client something untrue.

Statuses can be added, removed or replaced, and this is the last
word: the built-in `supports` conditions have already been applied
against `$post_type` by the time this runs, so a status kept off a
post type can be put back, and one that does not suit a post type
can be taken away. `$post_type` is empty when the whole vocabulary
is being asked for rather than one post type's share of it.

The first status in the list is what an event with no status of its
own reports, so order matters.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_statuses',
    function(
        array<string, array<string,,
        string $post_type
    ) {
        // Your code here.
        return array<string,;
    },
    10,
    2
);
```

## Parameters

- *`array<string,`* `array<string,` mixed>> $statuses  The statuses, keyed by slug.
- *`string`* `$post_type` Post type they are offered for, or empty for all.

## Files

- [includes/core/classes/event/class-status.php:204](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-status.php#L204)
```php
apply_filters( 'gatherpress_event_statuses', $statuses, $post_type )
```



[← All Hooks](Hooks.md)
