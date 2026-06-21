# gatherpress_register_static_map_providers


Fires when venue map providers are being registered.

Fires on `init` priority 0 — after all plugins have loaded but
before any default-priority `init` listener observes the
registry. Companion plugins should hook this (NOT `plugins_loaded`,
which is too early — the manager singleton may not yet exist)
and register their providers by calling
`$registry->register( new My_Map_Provider() )`. Core providers
(OSM) are already registered by this point.

## Auto-generated Example

```php
add_action(
   'gatherpress_register_static_map_providers',
    function( GatherPress\Manager $registry ) {
        // Your code here.
    }
);
```

## Parameters

- *`GatherPress\Manager`* `$registry` Provider registry.

## Files

- [includes/core/classes/venue/map/class-manager.php:274](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-manager.php#L274)
```php
do_action( 'gatherpress_register_static_map_providers', $this )
```



[← All Hooks](Hooks.md)
