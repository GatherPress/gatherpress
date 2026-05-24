# gatherpress_template_path


Filters the resolved template path returned by `Utility::locate_template()`.

Lets extension code (plugin, mu-plugin, theme, etc.) override or
replace GatherPress's default theme → block-template → fallback-dir
resolution chain — for example to point the calendar's iCal
templates at a custom directory, or to ship overridable templates
from a companion source via the same utility. Return an empty
string to signal "no template found"; callers will fall back to
their own default.

## Auto-generated Example

```php
add_filter(
   'gatherpress_template_path',
    function(
        string,
        string $file_name,
        string $fallback_dir
    ) {
        // Your code here.
        return string;
    },
    10,
    3
);
```

## Parameters

- `string` $resolved     Resolved absolute template path, or `''` if no candidate matched. Other variable names: `$resolved`
- *`string`* `$file_name` The template file name passed to `Utility::locate_template()`.
- *`string`* `$fallback_dir` Directory the bundled fallback was looked up in (may be empty).

## Files

- [includes/core/classes/class-utility.php:124](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-utility.php#L124)
```php
apply_filters( 'gatherpress_template_path', $resolved, $file_name, $fallback_dir )
```



[← All Hooks](Hooks.md)
