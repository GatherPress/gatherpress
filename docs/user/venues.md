# Venues

Venues define *where* an event takes place. They are managed separately so they can be reused across multiple events.

Note: For the next version, 0.34, the venue blocks are going to be completely redeveloped and there will be a new Venue Query block.

## Creating venues

Venues can be created from `Events > Venues > Add New` in the WordPress admin.


![Screenshot of the editor for adding a venue](./user-doc-media/20260110145730.png)

The Venue block allows you to add/edit:

- A title (the venue name)
- Full address  
- Phone number  
- Website  
- Whether you want to display the map, with its zoom level and map height  
- Latitude / Longitude are filled by default, but you can use custom ones

Standard content blocks allow you to add any other content.

Note:

- A venue does not need to be assigned to an event immediately.

## Venue reuse

Venues are designed to be reusable. Updating a venue updates in all events that reference it.

What this allows:

- Use the same venue for recurring events.
- Maintain a single source of truth for venue details.

## Venue display

Venue information is displayed on its own page, or in an event to which that specific venue has been assigned.

You can have an hybrid event with a physical venue and an online link. For this, you need to add both the Online event block and the Venue block in the event.

## Venue map

Every venue with a real address ships with a map, rendered in one of two modes set on the Venue Map block:

- **Interactive** — a live, pan-and-zoom map powered by Leaflet (OpenStreetMap) or Google Maps, depending on the mapping platform you pick under `Settings > GatherPress > Venues > Maps`. Requires JavaScript on the visitor's side.
- **Static image** — a pre-rendered PNG of the map, composited server-side from OpenStreetMap tiles on the first save and cached under `wp-content/uploads/gatherpress/static-maps/`. No JavaScript required, which makes it the right choice for email previews, reader modes, cached HTML, and visitors who block scripts. Attribution for OpenStreetMap and CartoDB is rendered alongside the image automatically.

The block's inspector sidebar lets you pick the render mode, zoom level (1–20), and height (100–800 px). You can mix modes across events — one event can show the static image, another the interactive map, both pointing at the same venue.

### Sitewide defaults

`Settings > GatherPress > Venues > Maps` exposes four defaults that apply to newly inserted Venue Map blocks:

- **Default Render Mode** — interactive or static image.
- **Default Zoom Level** — 1 (world) to 20 (street).
- **Default Height** — in pixels.
- **Default Map Type** — roadmap, satellite, hybrid, or terrain (Google Maps only; OpenStreetMap and static images ignore this).

Changing a default only affects blocks added afterwards — existing blocks keep the values the editor chose at insertion time, so a sitewide change never rewrites published content.

### Regeneration

Static images regenerate automatically when any input the image depends on changes — venue address, coordinates, zoom level, or block height. Each `(zoom, height)` combination is cached separately, so two events showing the same venue at different sizes each get a crisp PNG. Old images are cleaned up when the venue is updated or deleted.
