# gatherpress_venue_starter_patterns


Filters the array of venue starter pattern definitions.

Each entry is an associative array with `name`, `title`,
`description`, and `content` keys. Returned patterns are
registered with `core/post-content` `blockTypes` scoping plus
every post type declaring `gatherpress-venue-information`
support, so they appear in the new-venue chooser modal for any
post type acting as a venue source.

The `$post_types` array lets consumers tailor the returned
patterns to the post types about to receive them — useful for
companion plugins that register their own venue-acting post
type and want to swap a pattern in only when their post type
is in scope.


`includes/core/templates/venue/` directory.
support that the patterns will be registered against.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_starter_patterns',
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
- *`array`* `$post_types` Post type slugs declaring `gatherpress-venue-information`

## Files

- [includes/core/classes/venue/class-setup.php:361](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L361)
```php
apply_filters( 'gatherpress_venue_starter_patterns', $patterns, $post_types )
```



[← All Hooks](Hooks.md)
