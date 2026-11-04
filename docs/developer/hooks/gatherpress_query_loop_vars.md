# gatherpress_query_loop_vars


This filter is documented in includes/core/classes/blocks/class-event-query.php

## Auto-generated Example

```php
add_filter(
   'gatherpress_query_loop_vars',
    function(
        array<string, mixed>,
        $get_params,
        bool $inherited,
         = null
    ) {
        // Your code here.
        return mixed>;
    },
    10,
    3
);
```

## Parameters

- *`array<string,`* `mixed>` $query_args  Arguments to be passed to WP_Query.
- `$get_params` Other variable names: `array<string, mixed> $block_query The block's query attribute, or the`, `$attrs`, `$block_query`
- *`bool`* `$inherited` Whether the Query Loop inherits the main query.
- ``

## Files

- [includes/core/classes/class-shadow-source.php:170](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-shadow-source.php#L170)
```php
apply_filters(
			'gatherpress_query_loop_vars',
			$custom_args,
			$request->get_params(),
			false
		)
```

- [includes/core/classes/blocks/class-event-query.php:208](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/class-event-query.php#L208)
```php
apply_filters(
				'gatherpress_query_loop_vars',
				$query_args,
				$parsed_block['attrs']['query'],
				true,
			)
```

- [includes/core/classes/blocks/class-event-query.php:343](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/class-event-query.php#L343)
```php
apply_filters(
			'gatherpress_query_loop_vars',
			$query_args,
			$block_query,
			false
		)
```

- [includes/core/classes/blocks/class-event-query.php:537](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/class-event-query.php#L537)
```php
apply_filters(
			'gatherpress_query_loop_vars',
			$custom_args,
			$request->get_params(),
			false,
		)
```

- [includes/core/classes/blocks/class-calendar-query.php:491](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/class-calendar-query.php#L491)
```php
apply_filters( 'gatherpress_query_loop_vars', $query_args, $block_query, false )
```



[← All Hooks](Hooks.md)
