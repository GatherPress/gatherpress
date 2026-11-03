# gatherpress_register_rsvp_check_in_states


Fires so plugins can register check-in states.

A check-in state is a class that extends
`GatherPress\Core\Rsvp\Flag\Check_In_State`, for example a check-in
for people who joined online. The RSVP screen shows each registered
state in its column, actions and filter. Core registers `Check_In`
and `No_Show` before this fires.

```php
add_action( 'gatherpress_register_rsvp_check_in_states', function ( $registry ) {
    $registry->register( My_Plugin\Checked_In_Online::class );
} );
```

The full state contract and a worked example live in the
RSVP developer guide (`docs/developer/rsvp/README.md`).

## Auto-generated Example

```php
add_action(
   'gatherpress_register_rsvp_check_in_states',
    function( GatherPress\Check_In_Registry $registry ) {
        // Your code here.
    }
);
```

## Parameters

- *`GatherPress\Check_In_Registry`* `$registry` The check-in state registry.

## Files

- [includes/core/classes/rsvp/flag/class-check-in-registry.php:266](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/flag/class-check-in-registry.php#L266)
```php
do_action( 'gatherpress_register_rsvp_check_in_states', $this )
```



[← All Hooks](Hooks.md)
