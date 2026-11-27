# gatherpress_static_map_marker_colors


Filter the colors of the marker drawn on static OpenStreetMap images.

Static maps are rendered on the server, so CSS cannot change their
marker. Saved maps render again when these colors change.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_marker_colors',
    function( array<string, string> ) {
        // Your code here.
        return string>;
    }
);
```

## Parameters

- *`array<string,`* `string>` $colors
  - *`string`* `$border` Outer ring. Default `#ffffff`.
  - *`string`* `$fill` Body of the marker. Default `#dc3545`.
  - *`string`* `$outline` Thin line around the body. Default `#1e1e1e`.
  - *`string`* `$center` Dot in the middle. Default `#ffffff`.

## Files

- [includes/core/classes/venue/map/provider/class-osm.php:467](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/provider/class-osm.php#L467)
```php
apply_filters( 'gatherpress_static_map_marker_colors', self::DEFAULT_MARKER_COLORS )
```



[← All Hooks](Hooks.md)
