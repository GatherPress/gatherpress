# gatherpress_loaded


Fires once GatherPress has finished bootstrapping its core classes.

Subsystems and third party plugins use this to run setup work that
depends on other GatherPress classes already being instantiated —
for example, the RSVP provider registry consumes it to fire its own
`gatherpress_register_rsvp_types` action.

Fires on `plugins_loaded`, so any plugin can catch it. See the
plugin lifecycle guide (`docs/developer/plugin-lifecycle.md`).

## Auto-generated Example

```php
add_action(
   'gatherpress_loaded',
    function() {
        // Your code here.
    }
);
```

## Files

- [gatherpress.php:77](https://github.com/GatherPress/gatherpress/blob/develop/gatherpress.php#L77)
```php
do_action( 'gatherpress_loaded' )
```



[← All Hooks](Hooks.md)
