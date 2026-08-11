# Theme customizations

1. [Template overrides](#template-overrides)
2. [Theme supports](#theme-supports)
3. [CSS custom properties](#css-custom-properties)

## Template overrides

GatherPress provides different ways to customize its output via theme files. Some of this customization opportunities come from GatherPress, but the most are just pure WordPress. A site could provide one or more of the following templates from one of: 

- a child theme’s `/templates` folder (if child theme is active).
- the theme’s `/templates` folder.

### Default template overrides

Following the [default WordPress template hierarchy](https://developer.wordpress.org/themes/templates/template-hierarchy).

#### Events

- `archive-gatherpress_event.(html|php)`
- `single-gatherpress_event.(html|php)`
- `single-gatherpress_event-{post_name}.(html|php)`
- `embed-gatherpress_event.php`

Due to [a known issue](https://developer.wordpress.org/themes/templates/template-hierarchy/#embed-hierarchy) embed templates can only be created as `.php` files.

#### Venues

- `single-gatherpress_venue.(html|php)`
- `single-gatherpress_venue-{post_name}.(html|php)`
- `embed-gatherpress_venue.php`

#### Topics

- `taxonomy-gatherpress_topic.(html|php)`
- `taxonomy-gatherpress_topic-{term_slug}.(html|php)`

### Overriding plugin template

In addition to the default theme files, a theme author could add the following templates to override special templates, normally provided by the GatherPress plugin:

- `gatherpress_ical-download.php`
- `gatherpress_ical-feed.php`

## Theme supports

GatherPress does respect [theme_supports](https://developer.wordpress.org/reference/functions/current_theme_supports/) definitions and will output the following pieces only if the current theme supports it.

- When **`automatic-feed-links`** are supported, GatherPress will add `rel="alternate"` links to the `<head>` of each view, with the URLs to the relevant iCal feed links. This will be:

    - For all requests (`example.org/*`):
        - `example.org/feed/ical` (site-wide)
        - `example.org/event/feed/ical` (events archive)

    - For singular event requests (`example.org/event/my-sample-event`):
        - `example.org/feed/ical`
        - `example.org/event/feed/ical`
        - `example.org/event/my-sample-event/ical`
        - `example.org/venue/my-sample-venue/feed/ical` (if it's not an Online-Event)
        - `example.org/topic/my-sample-topic/feed/ical` (if a topic is selected)

    - For singular venue requests (`example.org/venue/my-sample-venue`):
        - `example.org/feed/ical`
        - `example.org/event/feed/ical`
        - `example.org/venue/my-sample-venue/feed/ical`

    - For topic term requests (`example.org/topic/my-sample-topic`):
        - `example.org/feed/ical`
        - `example.org/event/feed/ical`
        - `example.org/topic/my-sample-topic/feed/ical`

## CSS custom properties

Some GatherPress components read CSS custom properties so a theme can restyle them without overriding selectors or replacing assets. Set them anywhere the component inherits from — `:root`, a block wrapper, or `theme.json`'s `styles.css`.

They follow WordPress's own `--wp--preset--color--primary` shape: `--gatherpress--{component}--{property}`, with two dashes between segments.

Each one falls back to a WordPress global style before falling back to a hard-coded default, so a theme that defines its palette through `theme.json` usually gets something reasonable without setting these at all.

### Tooltip

| Property | Falls back to |
| --- | --- |
| `--gatherpress--tooltip--text-color` | `--wp--preset--color--base`, then `--wp--preset--color--background`, then `#fff` |
| `--gatherpress--tooltip--background-color` | `--wp--preset--color--contrast`, then `--wp--preset--color--primary`, then `#333` |

### Venue map

Applies to the interactive (Leaflet) map. Static maps are server-rendered images and are not affected.

| Property | Falls back to |
| --- | --- |
| `--gatherpress--venue-map--attribution-link-color` | `--wp--preset--color--primary`, then `#0073aa` |
| `--gatherpress--venue-map--marker-filter` | `none` |

`--gatherpress--venue-map--marker-filter` is **not a color**. The map marker is one of Leaflet's raster images, so the value has to be a valid CSS [`filter`](https://developer.mozilla.org/en-US/docs/Web/CSS/filter) list. Setting a color here does nothing.

To tint the default marker toward a theme color, combine `brightness(0) saturate(100%)` (which flattens the image to black) with hue and saturation adjustments:

```css
:root {
	--gatherpress--venue-map--marker-filter: brightness(0) saturate(100%) invert(28%)
		sepia(96%) saturate(1720%) hue-rotate(196deg);
}
```

Themes that would rather supply their own marker artwork should replace the icon rather than filter it.

