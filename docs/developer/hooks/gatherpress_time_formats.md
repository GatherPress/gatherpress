# gatherpress_time_formats


Filters the time formats offered as examples in GatherPress.

## Auto-generated Example

```php
add_filter(
   'gatherpress_time_formats',
    function( GatherPress\string[] $formats ) {
        // Your code here.
        return $formats;
    }
);
```

## Parameters

- *`GatherPress\string[]`* `$formats` PHP time formats.

## Files

- [includes/core/classes/class-utility.php:497](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-utility.php#L497)
```php
apply_filters( 'gatherpress_time_formats', $formats )
```



[← All Hooks](Hooks.md)
