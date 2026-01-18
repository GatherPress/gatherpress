# gatherpress_autoloader


Filters the registered autoloaders for GatherPress.

This filter allows developers to add or modify autoloaders for GatherPress. By using this filter,
namespaces and their corresponding paths can be registered.

## Example

```php
function gatherpress_awesome_autoloader( array $namespace ): array {
    $namespace['GatherPress_Awesome'] = __DIR__;

    return $namespace;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_awesome_autoloader' );
```

**Example:** The namespace `GatherPress_Awesome\Setup` would map to
`gatherpress-awesome/includes/classes/class-setup.php`.

## Parameters

- *`array`* `$registered_autoloaders` An associative array of namespaces and their paths.

## Returns

`array` Modified array of namespaces and their paths.

## Files

- [includes/core/classes/class-autoloader.php:59](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-autoloader.php#L59)
```php
apply_filters( 'gatherpress_autoloader', array() )
```



[← All Hooks](Hooks)
