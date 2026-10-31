# gatherpress_event_ended


This action is documented in includes/core/classes/event/class-scheduler.php

## Example

```php
// Purge external cache and trigger post-event workflows when an event concludes:
add_action(
    'gatherpress_event_ended',
    static function ( int $post_id ): void {
        wp_cache_delete( "my_custom_cache_{$post_id}", 'my_plugin' );
    }
);
```

## Parameters

- *`int`* `$post_id` The post ID of the event that ended.

## Files

- [includes/core/classes/event/class-scheduler.php:285](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-scheduler.php#L285)
```php
do_action( 'gatherpress_event_ended', $post_id )
```

- [includes/core/classes/event/class-scheduler.php:594](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-scheduler.php#L594)
```php
do_action( 'gatherpress_event_ended', $post_id )
```



[← All Hooks](Hooks.md)
