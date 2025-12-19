# gatherpress_pre_get_http_input


Short-circuit filter for HTTP input retrieval during testing.

Allows tests to completely bypass filter_input() and provide
their own values. Only available during unit tests for security.
Return a non-null value to short-circuit.

## Auto-generated Example

```php
add_filter(
   'gatherpress_pre_get_http_input',
    function(
        string $pre_value = null,
        int $type,
        string $var_name
    ) {
        // Your code here.
        return null;
    },
    10,
    3
);
```

## Parameters

- *`string|null`* `$pre_value` Pre-value to return instead of using filter_input.
- *`int`* `$type` Input type (INPUT_GET, INPUT_POST, etc.).
- *`string`* `$var_name` Variable name being requested.

## Files

- [includes/core/classes/class-utility.php:384](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-utility.php#L384)
```php
apply_filters( 'gatherpress_pre_get_http_input', null, $type, $var_name )
```



[← All Hooks](Hooks)
