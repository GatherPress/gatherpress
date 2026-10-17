# gatherpress_email_recipient_consent


Filters whether a recipient consents to a GatherPress email.

Lets a site set its own consent rule per kind of email, for example
requiring an explicit opt-in before a site-wide message goes out.


true for the `rsvp` context.

## Auto-generated Example

```php
add_filter(
   'gatherpress_email_recipient_consent',
    function(
        bool $consent,
        array $recipient,
        string $context
    ) {
        // Your code here.
        return $consent;
    },
    10,
    3
);
```

## Parameters

- *`bool`* `$consent` Whether the recipient consents. Defaults to the opt-in, or to
- *`array`* `$recipient` Recipient row from a mail path.
- *`string`* `$context` Mail path asking for consent.

## Returns

`bool` Whether the recipient consents.

## Files

- [includes/core/classes/class-mailer.php:170](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-mailer.php#L170)
```php
apply_filters(
			'gatherpress_email_recipient_consent',
			'rsvp' === $context || $this->is_eligible( $recipient ),
			$recipient,
			$context
		)
```



[← All Hooks](Hooks.md)
