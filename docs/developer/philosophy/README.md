# Close to core

GatherPress has one design rule that explains most of the others: **follow the WordPress way wherever we can**.

That is not modesty about our own ideas. It is the reason the plugin is small, the reason your theme already styles it, and the reason your existing knowledge of WordPress transfers straight across. If you know how to extend WordPress, you already know how to extend GatherPress.

## What "close to core" actually means

Whenever WordPress already has an answer, GatherPress uses it rather than inventing a parallel one.

| The thing | What GatherPress uses |
|---|---|
| Events and venues | Ordinary custom post types, with the block editor and revisions |
| Topics | An ordinary public taxonomy |
| RSVPs | Comments, with taxonomies for status, provider and flags |
| Settings | The Settings API, and the options table |
| Layouts | Block patterns, block templates and the Block Hooks API |
| Theming | `theme.json`, global styles and template overrides from the theme |
| Moving content | WordPress's own WXR import and export |
| Permissions | Capabilities and roles, not a bespoke permission layer |
| Admin UI | Core components and core functions such as `wp_admin_notice()` |

The payoff is compounding. RSVPs being comments means the comment query, comment meta, moderation and the REST API work on them already. Venues being posts means the block editor, revisions and the media library work on them already. None of that had to be built, and none of it can drift out of step with core.

### When we do add something of our own

Rarely, and it is additive rather than a replacement.

The clearest example is the one [custom table](../data-model/README.md#the-events-table), and it is worth being precise about what it is for, because it is easy to misread as "we gave up on post meta".

We did not. Event dates **are** post meta. `gatherpress_datetime` is the canonical value the editor and the REST API write, and it is the representation that travels with the content. The table sits alongside it and exists for **queries and scale**, which is a different job.

The reason is that every event query is a date-range query: upcoming, past, between two dates, ordered by start. `wp_postmeta` stores its values as `longtext` with no useful index, so a range comparison means casting every row. Indexed `datetime` columns turn that into a normal range scan, and they keep doing so on a site with tens of thousands of events. That was a scaling decision made deliberately at the start, not a retreat after post meta fell over.

The two stay in step automatically. On `wp_after_insert_post`, `Event\Setup::set_datetimes()` reads the `gatherpress_datetime` meta and writes the table row from it, so updating the meta updates the table. Nothing has to remember to do both.

That is the shape of an acceptable addition: core's storage still owns the content, the extra structure serves a job core's storage is not built for, and the departure is one table rather than one architecture. `Event\Query` joins it into `WP_Query` through the standard SQL clause filters, so blocks, Query Loops and the REST API keep behaving like they always did.

## Keeping the plugin focused

The second rule follows from an uncomfortable truth: **it is much easier to put things into a plugin than to pull them back out.**

Once a feature ships, people build on it. They write content that depends on it, workflows that assume it, and companion code that calls it. Removing it later breaks all of that, so in practice a feature that ships is a feature we maintain forever. That makes "should this go in?" a heavier question than it first looks, and it is why a good idea can still be the wrong thing for the plugin.

So the bar is not "is this useful?" Plenty of useful things do not belong here. The bar is closer to: **would most people running most events need this?**

If a feature serves a small group, it is companion plugin territory. That is not a polite refusal. It is where the feature can move faster, ship on its own schedule, and answer to the people who actually want it, without every other GatherPress user carrying its weight in their install.

### What that means in practice

- Core stays the shared foundation everyone needs.
- Specialized workflows live in companion plugins, built on the extension points core exposes.
- When an extension point is missing, that is a gap in core worth fixing. Adding a filter so a companion plugin can do the work is usually the better answer than doing the work in core.

If you are unsure which side of the line an idea falls on, open an issue and ask. That conversation is a normal part of how features get shaped here, not a hurdle before the real work.

## The community promise

GatherPress is built by and for the community that uses it. Two commitments come out of that:

**It stays free and open.** No paid tier holds a feature hostage, and nothing is withheld from the plugin to sell separately.

**Extending it is a first-class path, not a workaround.** The supports, hooks, patterns and slot fills documented here exist so somebody else can build what we decided not to. A companion plugin is not a second-best outcome; it is frequently the right one, and it is the mechanism this design deliberately leans on.

## Where this shows up in the code

Two conventions are worth knowing, because they are the philosophy made concrete:

**Features attach to supports, not to our post types.** GatherPress checks `post_type_supports( $post_type, 'gatherpress-event-date' )` rather than comparing against `gatherpress_event`. Declare the support on your own post type and event dates, RSVPs, venues and the blocks that read them all work on it. See [post type supports](../post-type-supports/README.md).

**Extension points come with their data.** The import and export system carries your data through WordPress's own WXR files rather than making you write an importer. Settings pages are added through the Settings API rather than a GatherPress-specific one. Templates are overridden from the theme, the way WordPress templates always have been.

## See also

- [Companion plugins](../companion-plugins/README.md), and the starter that scaffolds one
- [Post type supports](../post-type-supports/README.md), the main extension surface
- [Hook reference](../hooks/), every filter and action, generated from source
- [Data model](../data-model/README.md), where everything is stored and why
