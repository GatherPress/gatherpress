# gatherpress_event_starter_patterns


Filters the array of event starter pattern definitions.

Each entry is an associative array with `name`, `title`,
`description`, and `content` keys. Returned patterns are
registered with `core/post-content` `blockTypes` scoping plus
every post type declaring `gatherpress-event-date` support, so
they appear in the new-event chooser modal for any post type
acting as an event source.


`includes/core/templates/event/` directory.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_starter_patterns',
    function( array $patterns ) {
        // Your code here.
        return $patterns;
    }
);
```

## Parameters

- *`array`* `$patterns` Pattern definitions loaded from the

## Files

- [includes/core/classes/event/class-setup.php:273](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L273)
```php
apply_filters( 'gatherpress_event_starter_patterns', $patterns )
```



[← All Hooks](Hooks.md)
