# gatherpress_venue_map_tile_url


Filter the tile URL template used by the static venue map.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_tile_url',
    function( string $template ) {
        // Your code here.
        return $template;
    }
);
```

## Parameters

- *`string`* `$template` Tile URL with `{z}`, `{x}`, `{y}` placeholders.

## Files

- [includes/core/classes/class-venue-map.php:1102](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map.php#L1102)
```php
apply_filters( 'gatherpress_venue_map_tile_url', self::DEFAULT_TILE_URL )
```



[← All Hooks](Hooks.md)
