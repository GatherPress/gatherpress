# gatherpress_asset_critical


Filters whether an asset file is considered critical.

This filter allows modification of the critical flag for asset files,
which determines whether missing assets throw an Error in development
environments or silently return false.

## Auto-generated Example

```php
add_filter(
   'gatherpress_asset_critical',
    function(
        bool $critical,
        string $path,
        string $name
    ) {
        // Your code here.
        return $critical;
    },
    10,
    3
);
```

## Parameters

- *`bool`* `$critical` Whether file is mandatory for the plugin to work.
- *`string`* `$path` Full file path to the asset file.
- *`string`* `$name` Name of the asset being loaded.

## Returns

`bool` True if asset is critical, false otherwise.

## Files

- [includes/core/classes/class-assets.php:554](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-assets.php#L554)
```php
apply_filters( 'gatherpress_asset_critical', $critical, $path, $name )
```



[← All Hooks](Hooks.md)
