# Theme customizations

1. [Template overrides](#template-overrides)
2. [Theme supports](#theme-supports)

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
        - `example.org/event/feed/ical`

   - For singular event requests (`example.org/event/my-sample-event`):
        - `example.org/event/feed/ical`
        - `example.org/event/my-sample-event/ical`
        - `example.org/venue/my-sample-venue/feed/ical` (if its not an Online-Event)
        - `example.org/topic/my-sample-topic/feed/ical` (if a topic is selected)

   - For singular venue requests (`example.org/venue/my-sample-venue`):
        - `example.org/event/feed/ical`
        - `example.org/venue/my-sample-venue/feed/ical`

   - For topic term requests (`example.org/topic/my-sample-topic`):
        - `example.org/event/feed/ical`
        - `example.org/topic/my-sample-topic/feed/ical`
