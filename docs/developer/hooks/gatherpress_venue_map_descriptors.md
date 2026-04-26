# gatherpress_venue_map_descriptors


Filters the parsed descriptor map for a venue.

Companion plugins, multi-locale setups, or storage-layer overrides
can use this to drop entries they consider stale, add synthetic
descriptors (e.g. pre-rendered PNG files in a CDN), or rewrite URLs.
Outer key is provider slug, inner key is `{zoom}x{width}x{height}`.
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

- *`array<string,`* `array<string,` array<string, mixed>>> $descriptors Provider-keyed descriptor map.
- *`int`* `$post_id` Venue post ID.

## Files

- [includes/core/classes/venue/map/class-map.php:1108](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-map.php#L1108)
```php
apply_filters( 'gatherpress_venue_map_descriptors', $descriptors, $post_id )
```



[← All Hooks](Hooks.md)
