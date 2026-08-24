# gatherpress_settings_section


Fires so tabs that render via the GatherPress settings section action
(e.g. the Alpha sub-page) can emit their own content. Mirrors the
per-site settings page template.

## Auto-generated Example

```php
add_action(
   'gatherpress_settings_section',
    function( string $page ) {
        // Your code here.
    }
);
```

## Parameters

- *`string`* `$page` Prefixed page slug (e.g. `gatherpress_alpha`).

## Files

- [includes/templates/admin/settings/network-page.php:323](https://github.com/GatherPress/gatherpress/blob/develop/includes/templates/admin/settings/network-page.php#L323)
```php
do_action( 'gatherpress_settings_section', $gatherpress_current_page )
```

- [includes/templates/admin/settings/index.php:53](https://github.com/GatherPress/gatherpress/blob/develop/includes/templates/admin/settings/index.php#L53)
```php
do_action( 'gatherpress_settings_section', $page )
```



[← All Hooks](Hooks.md)
