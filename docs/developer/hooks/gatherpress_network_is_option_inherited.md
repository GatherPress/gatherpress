# gatherpress_network_is_option_inherited


Filters whether a specific GatherPress option is inherited from the network.

Returning false exempts the current site from network-level inheritance
for that option; returning true forces inheritance even if the network
config would otherwise leave it site-editable.

## Auto-generated Example

```php
add_filter(
   'gatherpress_network_is_option_inherited',
    function(
        bool $inherited,
        string $option,
        int $blog_id
    ) {
        // Your code here.
        return $inherited;
    },
    10,
    3
);
```

## Parameters

- *`bool`* `$inherited` Whether the option is inherited from the network.
- *`string`* `$option` The option key being resolved.
- *`int`* `$blog_id` The current site ID.

## Files

- [includes/core/classes/class-settings.php:805](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-settings.php#L805)
```php
apply_filters(
			'gatherpress_network_is_option_inherited',
			$inherited,
			$option,
			get_current_blog_id()
		)
```



[← All Hooks](Hooks.md)
