# gatherpress_event_starting


Fires when an event starts, for code that acts on it then.

Runs through WP-Cron at the start time, so on a quiet site it can run
a little late. Changing the start moves the run, and unpublishing,
trashing or deleting the event cancels it. An event published after
its start does not fire it.

## Example

```php
add_action(
    'gatherpress_event_starting',
    static function ( int $post_id ): void {
        // Post the event to a chat channel.
    }
);
```

## Parameters

- *`int`* `$post_id` The post ID of the event that is starting.

## Files

- [includes/core/classes/event/class-scheduler.php:313](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-scheduler.php#L313)
```php
do_action( 'gatherpress_event_starting', $post_id )
```



[← All Hooks](Hooks.md)
