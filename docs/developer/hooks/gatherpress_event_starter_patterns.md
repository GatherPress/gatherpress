# gatherpress_event_starter_patterns


Filters the array of event starter pattern definitions.

Each entry is an associative array with `name`, `title`,
`description`, and `content` keys. Returned patterns are
registered with `core/post-content` `blockTypes` scoping plus
every post type declaring `gatherpress-event-date` support, so
they appear in the new-event chooser modal for any post type
acting as an event source.

The `$post_types` array lets consumers tailor the returned
patterns to the post types about to receive them — useful for
companion plugins that register their own event-acting post
type and want to swap a pattern in only when their post type
is in scope.


`includes/core/templates/event/` directory.
support that the patterns will be registered against.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_starter_patterns',
    function(
        array $patterns,
        array $post_types
    ) {
        // Your code here.
        return $patterns;
    },
    10,
    2
);
```

## Parameters

- *`array`* `$patterns` Pattern definitions loaded from the
- *`array`* `$post_types` Post type slugs declaring `gatherpress-event-date`

## Files

- [includes/core/classes/event/class-setup.php:301](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L301)
```php
apply_filters( 'gatherpress_event_starter_patterns', $patterns, $post_types )
```



[← All Hooks](Hooks.md)
