# gatherpress_roles


Filter the list of roles for GatherPress.

This filter allows modification of the list of user roles used by GatherPress.
By default, GatherPress supports only the 'Organizers' role.


By default, it includes only the 'Organizers' role.

## Auto-generated Example

```php
add_filter(
   'gatherpress_roles',
    function( array $roles ) {
        // Your code here.
        return $roles;
    }
);
```

## Parameters

- *`array`* `$roles` An array of user roles supported by GatherPress.

## Returns

`array` The modified array of user roles.

## Files

- [includes/core/classes/settings/class-leadership.php:97](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/settings/class-leadership.php#L97)
```php
apply_filters( 'gatherpress_roles', $roles )
```



[← All Hooks](Hooks)
