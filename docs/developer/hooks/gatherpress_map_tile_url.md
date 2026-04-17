# gatherpress_map_tile_url


Filters the Leaflet tile layer URL used by the venue map.

## Auto-generated Example

```php
add_filter(
   'gatherpress_map_tile_url',
    function( string $url ) {
        // Your code here.
        return $url;
    }
);
```

## Parameters

- *`string`* `$url` Default tile URL template (CartoDB Positron).

## Files

- [includes/core/classes/class-settings.php:182](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-settings.php#L182)
```php
apply_filters( 'gatherpress_map_tile_url', self::MAP_TILE_URL )
```



[← All Hooks](Hooks.md)
