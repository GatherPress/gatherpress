# gatherpress_site_message_active_days


Filters the choices for how far back an active user's last RSVP can be.

Lets a site offer windows that suit how often it holds events, for
example 7 days for a weekly meetup.

## Auto-generated Example

```php
add_filter(
   'gatherpress_site_message_active_days',
    function( GatherPress\int[] $days ) {
        // Your code here.
        return $days;
    }
);
```

## Parameters

- *`GatherPress\int[]`* `$days` Numbers of days to offer. Default 30, 90, 180 and 365.

## Returns

`GatherPress\int[]` Numbers of days to offer.

## Files

- [includes/core/classes/admin/class-email.php:266](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/admin/class-email.php#L266)
```php
apply_filters( 'gatherpress_site_message_active_days', self::ACTIVE_DAYS )
```



[← All Hooks](Hooks.md)
