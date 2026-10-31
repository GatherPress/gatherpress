# gatherpress_site_message_subject


Filters the subject line of a site-wide message.

Lets a site brand the subject, for example with a prefix, including
the "Message from" default used when the sender leaves it blank.

## Auto-generated Example

```php
add_filter(
   'gatherpress_site_message_subject',
    function( string $subject ) {
        // Your code here.
        return $subject;
    }
);
```

## Parameters

- *`string`* `$subject` Email subject line.

## Returns

`string` Email subject line.

## Files

- [includes/core/classes/admin/class-email.php:725](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/admin/class-email.php#L725)
```php
apply_filters( 'gatherpress_site_message_subject', $subject )
```



[← All Hooks](Hooks.md)
