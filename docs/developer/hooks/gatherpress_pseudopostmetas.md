# gatherpress_pseudopostmetas


Filters the list of data-names and their respective export- and import-callbacks.

The filter allows to hook into WordPress' native import & export processes,
when post types of the GatherPress plugin are being migrated.
That can be helpful, if you want to import event- or venue-data from another plugin.

## Example

Example use of the filter to illustrate function signatures for the callbacks.

```php
\add_filter(
    'gatherpress_pseudopostmetas',
    function ( array $pseudopostmetas ): array {
        $pseudopostmetas['my_gatherpress_extension_data_name'] = [
            'export_callback' => function ( WP_Post $post ): string {
                // Do something with $post.
                // Query & prepare custom data
                // to exported with the current post.
                return 'my_gatherpress_extension_data';
            },
            'import_callback' => function (int $post_id, $meta_value ): void {
                // Save data for given post_id to a custom location,
                // when data should not end up in the postmeta table.
                return;
            },
        ];
        return $pseudopostmetas;
    }
);
```

## Parameters

- *`array`* `$pseudopostmetas` List of data-names and their respective export- and import-callbacks.

## Returns

`array` 

## Files

- [includes/core/classes/class-migrate.php:84](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-migrate.php#L84)
```php
apply_filters( 'gatherpress_pseudopostmetas', $this->pseudopostmetas )
```



[← All Hooks](Hooks)
