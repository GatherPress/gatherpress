# gatherpress_venue_map_descriptors


Filters the parsed descriptor map for a venue.

Companion plugins, multi-locale setups, or storage-layer overrides
can use this to drop entries they consider stale, add synthetic
descriptors (e.g. pre-rendered PNG files in a CDN), or rewrite URLs.
Callers of this method already tolerate empty maps, so returning
`[]` is a valid "suppress all" escape hatch.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_descriptors',
    function(
        array<string, array<string,,
        int $post_id
    ) {
        // Your code here.
        return array<string,;
    },
    10,
    2
);
```

## Parameters

- *`array<string,`* `array<string,` mixed>> $descriptors Parsed descriptor map keyed by combo.
- *`int`* `$post_id` Venue post ID.

## Files

- [includes/core/classes/class-venue-map.php:951](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map.php#L951)
```php
apply_filters( 'gatherpress_venue_map_descriptors', $descriptors, $post_id )
```



[← All Hooks](Hooks.md)
