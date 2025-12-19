# gatherpress_pre_get_wp_referer


Short-circuit filter for wp_get_referer() during testing.

Allows tests to completely bypass wp_get_referer() and provide
their own referer values. Only available during unit tests for security.
Return a non-null value to short-circuit.

## Auto-generated Example

```php
add_filter(
   'gatherpress_pre_get_wp_referer',
    function( string|false $pre_value = null ) {
        // Your code here.
        return null;
    }
);
```

## Parameters

- *`string|false|null`* `$pre_value` Pre-value to return instead of using wp_get_referer().

## Files

- [includes/core/classes/class-utility.php:439](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-utility.php#L439)
```php
apply_filters( 'gatherpress_pre_get_wp_referer', null )
```



[← All Hooks](Hooks)
