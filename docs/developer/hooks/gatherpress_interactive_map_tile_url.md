# gatherpress_interactive_map_tile_url


Filters the Leaflet tile layer URL used by the venue map.

## Auto-generated Example

```php
add_filter(
   'gatherpress_interactive_map_tile_url',
    function( string $url ) {
        // Your code here.
        return $url;
    }
);
```

## Parameters

- *`string`* `$url` Default tile URL template (CartoDB Positron).

## Files

- [includes/core/classes/class-settings.php:217](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-settings.php#L217)
```php
apply_filters( 'gatherpress_interactive_map_tile_url', self::MAP_TILE_URL )
```



[← All Hooks](Hooks.md)
