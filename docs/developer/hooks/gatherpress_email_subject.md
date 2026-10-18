# gatherpress_email_subject


Filters the subject of an event email.

Covers the update an editor sends from an event and the confirmation
an attendee gets when they move off the waiting list. Lets a site
brand or reword the subject, for example with a site prefix.

## Auto-generated Example

```php
add_filter(
   'gatherpress_email_subject',
    function(
        string $subject,
        int $post_id
    ) {
        // Your code here.
        return $subject;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$subject` Email subject line.
- *`int`* `$post_id` Event post ID.

## Returns

`string` Email subject line.

## Files

- [includes/core/classes/event/class-email.php:332](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-email.php#L332)
```php
apply_filters( 'gatherpress_email_subject', $subject, $post_id )
```



[← All Hooks](Hooks.md)
