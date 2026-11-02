# Multisite

GatherPress runs on a multisite network, and the short version is that **each site is its own installation**. A site has its own events, its own venues, its own topics, its own RSVPs and its own events table. Nothing is shared between sites unless the network explicitly says so.

Two things are network-scoped, and both are opt-in: [settings inheritance](../settings/multisite.md) and parts of [uninstall](#uninstall).

## Activation

**Network activating** creates the [events table](../data-model/README.md#the-events-table) on every site in the network. `Setup::activate_gatherpress_plugin()` receives WordPress's `$network_wide` flag, walks `get_sites()`, and runs the table creation inside `switch_to_blog()` for each one.

**Activating on a single site** creates the table for that site only, which is the same path a single-site install takes.

### Sites created later

A site added after the plugin was network activated still gets its table. `Setup::on_site_create()` listens on `wp_initialize_site`, checks `is_plugin_active_for_network()`, and creates the table on the new site.

That check is what makes the behavior correct rather than merely convenient: if GatherPress is activated per-site rather than network wide, a brand new site should not get GatherPress tables it never asked for, and it does not.

### Sites deleted later

`Setup::on_site_delete()` hooks `wpmu_drop_tables` and adds the site's events table to the list WordPress drops. Deleting a site takes its GatherPress data with it, with no orphan table left behind.

## Settings

By default every site has its own settings, stored in its own options table under `gatherpress_settings`.

A network admin can turn on **inheritance**, which is a separate network option, `gatherpress_network_settings`, holding a list of setting keys the network owns:

```php
array(
	'enabled'   => true,
	'inherited' => array( 'map_platform', 'date_format', /* … */ ),
)
```

For any key in that list, `Settings::get()` reads from the network option instead of the site's own, so every site sees the network's value and the field is shown disabled in the site's settings screen with the inherited value displayed.

Two details worth knowing before you write code against this:

- **The network admin screen is exempt.** `is_option_inherited()` returns false when `is_network_admin()` is true, because that is where super admins edit the network values and the fields have to stay editable. Anything that needs the real answer regardless of screen has to ask for it directly rather than going through `Settings::get()`.
- **The decision is filterable.** `gatherpress_network_is_option_inherited` lets a companion plugin exempt a particular site from inheritance for a particular key, or force it.

The full mechanism, including how the Tools tab routes an import or export to the right store, is in [settings on multisite](../settings/multisite.md).

## Uninstall

Deleting a plugin from the network Plugins screen is a single action with network-wide consequences, so GatherPress lets the network decide how far it reaches.

The network chooses one of two postures:

- **The network decides for every site.** One set of choices is applied everywhere.
- **Each site decides for itself.** Every site's own uninstall preferences are honored, and a site that opted into nothing keeps everything.

Mechanically, `Uninstall\Base::run()` walks the network once. For each site it calls `applies()` inside `switch_to_blog()`, so every task asks the question in that site's context and reads that site's preferences. Subclasses never call `is_multisite()` themselves.

Resources no single site owns are handled separately by `applies_to_network()`: the network settings option, and shared `usermeta`, which lives in one table for the whole network. A network that leaves those off keeps them even when individual sites clean themselves up.

`Uninstall\Preferences` reads the stored settings directly rather than through `Settings::get()`, for exactly the reason above: an uninstall started from the network Plugins screen runs in network admin context, where `Settings::get()` would report every inherited option as site-owned. Blog id `0` is the network's own answer, which no site can hold.

## What is shared and what is not

| Thing | Scope |
|---|---|
| Events, venues, topics, RSVPs | Per site |
| The `gatherpress_events` table | Per site, one per site prefix |
| Post meta, comment meta, term meta | Per site |
| Settings (`gatherpress_settings`) | Per site, unless the key is inherited |
| Network settings (`gatherpress_network_settings`) | Network |
| User meta (`gatherpress_timezone` and friends) | **Network.** WordPress stores `usermeta` once for the whole network |

That last row is the one that surprises people. A member who sets their timezone on one site has set it everywhere on the network, because that is how WordPress stores user meta. It is also why uninstall treats user meta as a network-level decision rather than a per-site one.

## Writing code that works on both

- **Do not assume a single site.** Anything that touches every site needs `get_sites()` and `switch_to_blog()`, and needs `restore_current_blog()` even on the paths that bail early.
- **Prefixes are per site.** `$wpdb->prefix` changes inside `switch_to_blog()`. Build table names from it at the point of use rather than caching one at load time.
- **Capabilities differ.** Site settings are gated on `manage_options`; anything network-scoped is gated on `manage_network_options`. They are not interchangeable, and a super admin has both while a site administrator has only the first.
- **`get_site_option()` returns `false` on single site**, not an empty array, so type-check before treating the result as one.

## Tests

Multisite behavior is covered by a separate PHPUnit suite. Tests that need a network carry `@group multisite`, which `phpunit.xml.dist` excludes, so they do not run in a normal local `npm run test:unit:php`.

Run them with:

```bash
npm run test:unit:php:multisite
```

CI runs both suites and merges the coverage before the pull request coverage check. Two consequences worth knowing:

- **Never remove `@group multisite`** from a class that carries it. Those tests exist and run.
- **Never add `@codeCoverageIgnore` to a multisite-only branch** such as a `switch_to_blog()` or `is_plugin_active_for_network()` path. It is covered by the multisite run. A class showing 0% coverage usually means the multisite suite is failing, not that the code is untested.

## See also

- [Settings on multisite](../settings/multisite.md), inheritance and the network settings screen
- [Data model](../data-model/README.md), what is stored where
- [Plugin lifecycle](../plugin-lifecycle.md), the load sequence on every site
