# gatherpress_sub_pages


Filters the list of GatherPress sub pages.

Allows a companion plugin or theme to extend GatherPress settings
by adding additional sub pages to the settings page.

## Auto-generated Example

```php
add_filter(
   'gatherpress_sub_pages',
    function( array $sub_pages ) {
        // Your code here.
        return $sub_pages;
    }
);
```

## Parameters

- *`array`* `$sub_pages` The array of sub pages.

## Returns

`array` Modified array of sub pages.

## Files

- [includes/core/classes/class-settings.php:917](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-settings.php#L917)
```php
apply_filters( 'gatherpress_sub_pages', array() )
```



[← All Hooks](Hooks.md)
