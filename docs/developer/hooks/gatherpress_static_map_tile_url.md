# gatherpress_static_map_tile_url


Filter the tile URL template used by the OSM static map provider.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_tile_url',
    function( string $template ) {
        // Your code here.
        return $template;
    }
);
```

## Parameters

- *`string`* `$template` Tile URL with `{z}`, `{x}`, `{y}` placeholders.

## Files

- [includes/core/classes/venue/map/provider/class-osm.php:347](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/provider/class-osm.php#L347)
```php
apply_filters( 'gatherpress_static_map_tile_url', self::DEFAULT_TILE_URL )
```



[← All Hooks](Hooks.md)
