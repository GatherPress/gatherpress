# gatherpress_email_recipients


Filters one batch of recipients of a GatherPress email.

Lets a site narrow or extend who a batch goes to. Mailer still checks
each recipient's consent before sending.

## Auto-generated Example

```php
add_filter(
   'gatherpress_email_recipients',
    function(
        array $recipients,
        string $type,
        array $payload
    ) {
        // Your code here.
        return $recipients;
    },
    10,
    3
);
```

## Parameters

- *`array`* `$recipients` Recipient rows in this batch.
- *`string`* `$type` The mail type, e.g. `event` or `site_message`.
- *`array`* `$payload` Plain data the email was queued with.

## Returns

`array` Recipient rows to send this batch to.

## Files

- [includes/core/classes/class-mailer.php:551](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-mailer.php#L551)
```php
apply_filters(
			'gatherpress_email_recipients',
			$batch['recipients'],
			$type,
			$payload
		)
```



[← All Hooks](Hooks.md)
