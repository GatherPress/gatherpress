# gatherpress_email_pre_enqueue_job


Filters the mail enqueue call to take over scheduling.

Return any non-null value to suppress both the WP-Cron dedup check
and the `wp_schedule_single_event()` call. A companion plugin that
hooks this filter owns the full scheduling path end-to-end,
including its own dedup since the jobs by-pass `wp_next_scheduled()`.
Mirrors the core `pre_*` filter convention: `null` means "pass
through to the default"; everything else, including falsy values
like `false`, `0`, and `''`, short-circuits.

## Auto-generated Example

```php
add_filter(
   'gatherpress_email_pre_enqueue_job',
    function(
        mixed $short_circuit,
        string $hook,
        array $args
    ) {
        // Your code here.
        return $short_circuit;
    },
    10,
    3
);
```

## Parameters

- *`mixed`* `$short_circuit` Non-null to suppress the default enqueue.
- *`string`* `$hook` Action hook name fired when the job runs.
- *`array`* `$args` Args passed to the action hook.

## Returns

`mixed` Null to let WP-Cron queue the job, anything else when another scheduler took it.

## Files

- [includes/core/classes/class-mailer.php:744](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-mailer.php#L744)
```php
apply_filters( 'gatherpress_email_pre_enqueue_job', null, $hook, $args )
```



[← All Hooks](Hooks.md)
