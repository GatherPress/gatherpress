# gatherpress_venue_starter_patterns


Filters the array of venue starter pattern definitions.

Each entry is an associative array with `name`, `title`,
`description`, and `content` keys, plus an optional `postTypes`
key (an array of post type slugs) narrowing that one pattern to
specific post types. Entries without `postTypes` register
against every post type declaring `gatherpress-venue-information`
support, so they appear in the new-venue chooser modal for any
post type acting as a venue source.

Prefer this filter over calling `register_block_pattern()`
directly: definitions inherit the support-resolved post type
list (a companion post type declaring the support is included
automatically — no slugs to enumerate), the `core/post-content`
scoping that surfaces patterns in the chooser modal is applied
for you, and the bundled defaults arrive in the same array so
they can be reordered, modified, or removed — not just
appended to.

The `$post_types` array lets consumers tailor the returned
patterns to the post types about to receive them — useful for
companion plugins that register their own venue-acting post
type and want to swap a pattern in only when their post type
is in scope.

narrow a single pattern's registration.

`includes/core/templates/venue/` directory.
support that patterns without their own
`postTypes` key will be registered against.

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

- [includes/core/classes/venue/class-setup.php:375](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L375)
```php
apply_filters( 'gatherpress_venue_starter_patterns', $patterns, $post_types )
```



[← All Hooks](Hooks.md)
