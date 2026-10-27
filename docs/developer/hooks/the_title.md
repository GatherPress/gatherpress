# the_title

## Auto-generated Example

```php
add_filter(
   'the_title',
    function(
        $post_title,
        $ID
    ) {
        // Your code here.
        return $post_title;
    },
    10,
    2
);
```

## Parameters

- `$post_title`
- `$ID`

## Files

- [includes/core/classes/class-shadow-source.php:426](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-shadow-source.php#L426)
```php
apply_filters( 'the_title', $post->post_title, $post->ID )
```



[← All Hooks](Hooks.md)
