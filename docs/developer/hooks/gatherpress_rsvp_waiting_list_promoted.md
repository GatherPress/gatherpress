# gatherpress_rsvp_waiting_list_promoted


This action is documented in includes/core/classes/rsvp/class-rsvp.php

## Auto-generated Example

```php
add_action(
   'gatherpress_rsvp_waiting_list_promoted',
    function(
        int $post_id,
        GatherPress\State $state
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$post_id` Event post ID.
- *`GatherPress\State`* `$state` The promoted RSVP.

## Files

- [includes/core/classes/rsvp/class-rsvp.php:416](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/class-rsvp.php#L416)
```php
do_action( 'gatherpress_rsvp_waiting_list_promoted', $post_id, $state )
```

- [includes/core/classes/rsvp/class-rsvp.php:452](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/class-rsvp.php#L452)
```php
do_action( 'gatherpress_rsvp_waiting_list_promoted', $post_id, $state )
```



[← All Hooks](Hooks.md)
