# gatherpress_venue_starter_patterns


Filters the array of venue starter pattern definitions.

Each entry is an associative array with `name`, `title`,
`description`, and `content` keys. Returned patterns are
registered with `core/post-content` `blockTypes` scoping plus
every post type declaring `gatherpress-venue-information`
support, so they appear in the new-venue chooser modal for any
post type acting as a venue source.


`includes/core/templates/venue/` directory.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_starter_patterns',
    function( array $patterns ) {
        // Your code here.
        return $patterns;
    }
);
```

## Parameters

- *`array`* `$patterns` Pattern definitions loaded from the

## Files

- [includes/core/classes/venue/class-setup.php:319](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L319)
```php
apply_filters( 'gatherpress_venue_starter_patterns', $patterns )
```



[← All Hooks](Hooks.md)
