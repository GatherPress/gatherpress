# gatherpress_email_subject


Filters the event update email subject.

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

## Files

- [includes/core/classes/event/class-rest-api.php:577](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-rest-api.php#L577)
```php
apply_filters( 'gatherpress_email_subject', $subject, $post_id )
```



[← All Hooks](Hooks.md)
