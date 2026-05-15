# gatherpress_interactive_map_tile_attribution


Filters the attribution HTML rendered with the venue map.

Override alongside `gatherpress_interactive_map_tile_url` when switching
providers so the correct credits are displayed.

## Auto-generated Example

```php
add_filter(
   'gatherpress_interactive_map_tile_attribution',
    function( string $attribution ) {
        // Your code here.
        return $attribution;
    }
);
```

## Parameters

- *`string`* `$attribution` Default attribution HTML.

## Files

- [includes/core/classes/class-settings.php:252](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-settings.php#L252)
```php
apply_filters( 'gatherpress_interactive_map_tile_attribution', $default )
```



[← All Hooks](Hooks.md)
