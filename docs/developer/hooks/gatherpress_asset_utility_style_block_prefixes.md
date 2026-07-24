# gatherpress_asset_utility_style_block_prefixes


Filters additional block-name prefixes whose blocks should
auto-enqueue the GatherPress utility stylesheet.

Companion plugins and themes can use this filter to share the
utility CSS with their own blocks (e.g. `gatherpress-awesome/`).
The `gatherpress/` prefix is appended after this filter runs and
cannot be removed through it.

## Auto-generated Example

```php
add_filter(
   'gatherpress_asset_utility_style_block_prefixes',
    function( GatherPress\string[] $prefixes ) {
        // Your code here.
        return $prefixes;
    }
);
```

## Parameters

- *`GatherPress\string[]`* `$prefixes` Additional block-name prefixes to match.

## Files

- [includes/core/classes/class-assets.php:298](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-assets.php#L298)
```php
apply_filters( 'gatherpress_asset_utility_style_block_prefixes', array() )
```



[← All Hooks](Hooks.md)
