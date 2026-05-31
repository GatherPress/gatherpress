# Event duration filters

GatherPress lets developers customize the event **Duration** control in the
block editor through two JavaScript filters:

1. [`gatherpress.durationOptions`](#gatherpressdurationoptions) — change the list of selectable durations.
2. [`gatherpress.durationDefault`](#gatherpressdurationdefault) — choose which duration is selected by default.

Both are registered with `wp.hooks.addFilter` (the `@wordpress/hooks` package)
and follow the [JavaScript hook naming convention](hooks-naming-convention.md)
(`gatherpress.camelCase`).

When the editor shows the event date and time, it renders either the
**Duration** select (a preset such as "2 hours") or the **Date Time End** picker
(an absolute end time). The Duration select is shown whenever the event's end
matches one of the duration options; picking "Set an end time…" switches to the
absolute picker.

## `gatherpress.durationOptions`

Filters the array of duration presets shown in the Duration select.

Each option is an object with:

- `label` (`string`) — the text shown in the dropdown.
- `value` (`number | false`) — the duration in hours, or `false` for the
  "Set an end time…" entry that switches to the absolute end-time picker.

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
	'gatherpress.durationOptions',
	'my-plugin/duration-options',
	( options ) => [
		{ label: '3 hours', value: 3 },
		{ label: '6 hours', value: 6 },
		{ label: 'All day', value: 24 },
		// Keep a "Set an end time…" entry if you still want the absolute
		// end-time picker to be reachable.
		{ label: 'Set an end time…', value: false },
	]
);
```

The value you return replaces the default list entirely, so include every
option you want available (and the `false` entry if you want to keep the
absolute end-time picker).

## `gatherpress.durationDefault`

Filters the preferred default duration, in hours, for a new event. The default
is `2`.

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
	'gatherpress.durationDefault',
	'my-plugin/duration-default',
	() => 6
);
```

The returned value is honored only when it matches one of the available
`durationOptions`. If it does not (for example, you return `6` but `6` is not in
the list), GatherPress falls back to the first real duration in the list so the
Duration select always has a matching preset. This guard prevents a default that
maps to no option, which would otherwise hide the Duration select behind the
absolute end-time picker.

## How the two filters work together

The default duration is resolved against the (possibly filtered) options:

- If `gatherpress.durationDefault` returns a value that is one of the
  `durationOptions`, that value is the default.
- Otherwise the first option with a numeric `value` (skipping the
  "Set an end time…" entry) becomes the default.
- With the built-in options unchanged, the default stays `2` hours.

So if you provide a custom `durationOptions` list that does not include `2`, you
do not have to set `gatherpress.durationDefault` as well — the first duration in
your list is used automatically. Set `gatherpress.durationDefault` only when you
want a specific entry (other than the first) to be the default.
