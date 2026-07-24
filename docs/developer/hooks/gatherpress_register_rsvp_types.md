# gatherpress_register_rsvp_types


Fires so plugins can register custom RSVP providers.

A provider defines a new RSVP identity source — a membership
system, an external ticketing platform, and so on. Register one
by passing an instance of a
`GatherPress\Core\Rsvp\Response\Provider\Base` subclass to the
registry. Core registers the `user` and `email` providers before
this fires.

```php
add_action( 'gatherpress_register_rsvp_types', function ( $registry ) {
    $registry->register( new My_Plugin\Membership_Provider() );
} );
```

The full provider contract and a worked example live in the
RSVP developer guide (`docs/developer/rsvp/README.md`).

## Auto-generated Example

```php
add_action(
   'gatherpress_register_rsvp_types',
    function( GatherPress\Provider_Registry $registry ) {
        // Your code here.
    }
);
```

## Parameters

- *`GatherPress\Provider_Registry`* `$registry` The RSVP provider registry.

## Files

- [includes/core/classes/rsvp/response/class-provider-registry.php:215](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/response/class-provider-registry.php#L215)
```php
do_action( 'gatherpress_register_rsvp_types', $this )
```



[← All Hooks](Hooks.md)
