# gatherpress_date_formats


Filters the date formats offered as examples in GatherPress.

A format that is not on this list still saves and still renders;
it simply arrives through the Custom field rather than the list.

## Auto-generated Example

```php
add_filter(
   'gatherpress_date_formats',
    function( GatherPress\string[] $formats ) {
        // Your code here.
        return $formats;
    }
);
```

## Parameters

- *`GatherPress\string[]`* `$formats` PHP date formats.

## Files

- [includes/core/classes/class-utility.php:470](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-utility.php#L470)
```php
apply_filters( 'gatherpress_date_formats', $formats )
```



[← All Hooks](Hooks.md)
