# Theme customizations

GatherPress provides different ways to customize its output via theme files. Some of this customization oppurtunities come from GatherPress, but the most are just pure WordPress. A site could provide one or more of the following templates from one of: 

- a child theme’s `/templates` folder (if child theme is active).
- the theme’s `/templates` folder.

## Default template overrides

Following the [default WordPress template hierachy](https://developer.wordpress.org/themes/templates/template-hierarchy).

### Events

- `archive-gatherpress_event.(html|php)`
- `single-gatherpress_event.(html|php)`
- `single-gatherpress_event-{post_name}.(html|php)`
- `embed-gatherpress_event.php`

Due to [a known issue](https://developer.wordpress.org/themes/templates/template-hierarchy/#embed-hierarchy) embed templates can only be created as `.php` files.

### Venues

- `single-gatherpress_venue.(html|php)`
- `single-gatherpress_venue-{post_name}.(html|php)`
- `embed-gatherpress_venue.php`

### Topics

- `taxonomy-gatherpress_topic.(html|php)`
- `taxonomy-gatherpress_topic-{term_slug}.(html|php)`

## Overriding plugin template

In addition to the default theme files, a theme author could add the following templates to override special templates, normally provided by the GatherPress plugin:

- `gatherpress_ical-download.php`
- `gatherpress_ical-feed.php`