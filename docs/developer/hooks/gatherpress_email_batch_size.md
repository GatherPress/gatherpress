# gatherpress_email_batch_size


Filters how many recipients one GatherPress mail job sends to.

Lower it on hosts with a short PHP time limit or a strict mail rate
limit, so each cron job finishes in time.

## Auto-generated Example

```php
add_filter(
   'gatherpress_email_batch_size',
    function(
        int $batch_size,
        string $type
    ) {
        // Your code here.
        return $batch_size;
    },
    10,
    2
);
```

## Parameters

- *`int`* `$batch_size` Recipients per batch. Default 50.
- *`string`* `$type` The mail type, e.g. `event` or `site_message`.

## Returns

`int` Recipients per batch.

## Files

- [includes/core/classes/class-mailer.php:660](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-mailer.php#L660)
```php
apply_filters( 'gatherpress_email_batch_size', 50, $type )
```



[← All Hooks](Hooks.md)
