# gatherpress_is_alpha_active


Filters whether GatherPress Alpha is considered active.

Allows tests to override the constant check.

## Auto-generated Example

```php
add_filter(
   'gatherpress_is_alpha_active',
    function( bool $is_alpha_active ) {
        // Your code here.
        return $is_alpha_active;
    }
);
```

## Parameters

- *`bool`* `$is_alpha_active` Whether GatherPress Alpha is active.

## Files

- [includes/core/classes/class-setup.php:466](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-setup.php#L466)
```php
apply_filters( 'gatherpress_is_alpha_active', defined( 'GATHERPRESS_ALPHA_VERSION' ) )
```



[← All Hooks](Hooks.md)
