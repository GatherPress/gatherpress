# gatherpress_import


Fires for every GatherPress data to be imported.

## Auto-generated Example

```php
add_action(
   'gatherpress_import',
    function( array ) {
        // Your code here.
    }
);
```

## Parameters

- `array` $post_data_raw Unprocessesd 'gatherpress_event' post being imported. Other variable names: `$post_data_raw`

## Files

- [includes/core/classes/class-import.php:91](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-import.php#L91)
```php
do_action( 'gatherpress_import', $post_data_raw )
```



[← All Hooks](Hooks)
