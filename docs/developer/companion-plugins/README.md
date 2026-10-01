# Companion plugins

A companion plugin is a plugin that extends GatherPress rather than being part of it. It is the intended home for anything that serves a specific group rather than most people running most events, and GatherPress is built to make that a first-class path rather than a workaround. [Close to core](../philosophy/README.md) explains why the line is drawn where it is.

## Start from the template

[**GatherPress Awesome**](https://github.com/GatherPress/gatherpress-awesome) is a starter plugin: an example of how to structure and organize a companion plugin. It is a GitHub template repository, so you can generate your own from it in one click.

It is not meant to ship as-is. Clone it, rename it, and build on the scaffold.

What you get already wired up:

- **A dependency on GatherPress**, declared with `Requires Plugins: gatherpress`, so WordPress 6.5 and later shows the relationship on the Plugins screen and refuses to activate your plugin without it.
- **A visible failure mode**, in the form of an admin notice if GatherPress is not loaded for some other reason, instead of a silent no-op.
- **A duplicate-folder guard**, which bails when a sibling copy of the same plugin is already loaded.
- **Registration with GatherPress's coexistence guard**, so two folders of your plugin cannot both activate.
- **Your namespace on GatherPress's autoloader**, so adding `includes/classes/class-foo.php` is enough to load `GatherPress_Awesome\Foo`.
- **A settings sub-page** inside Settings, GatherPress, which is the smallest useful example of hooking into GatherPress's own UI.
- **A Playground blueprint** that boots WordPress with GatherPress, your companion, and [gatherpress-demo-data](https://github.com/GatherPress/gatherpress-demo-data) already installed, so anyone can try your plugin from a link.
- **An `uninstall.php` scaffold** that walks the multisite blog list and cleans up after itself.

The repository's own README has the rename checklist and the file layout. Follow it exactly on the naming: the folder name, the entry filename and the slug passed to the coexistence guard all have to match.

## The two things worth understanding

Most of the scaffold is ordinary WordPress plugin structure. Two pieces are GatherPress-specific.

### The autoloader

GatherPress exposes its class autoloader through a filter, so your plugin does not need one of its own:

```php
function gatherpress_awesome_autoloader( array $namespace ): array {
	$namespace['GatherPress_Awesome'] = __DIR__;

	return $namespace;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_awesome_autoloader' );
```

`GatherPress_Awesome\Setup` then resolves to `gatherpress-awesome/includes/classes/class-setup.php`, using the same file-naming convention as core.

### The coexistence guard

Fire `gatherpress_register_coexistence_guard` with your slug, namespace and main plugin file, and GatherPress makes sure only one copy of your plugin runs when somebody ends up with two folders of it. This is the same mechanism GatherPress applies to itself.

## Where to extend

Before writing a feature, check whether an extension point already covers it. Most do.

| You want to | Start here |
|---|---|
| Put event or venue features on your own post types | [Post type supports](../post-type-supports/README.md) |
| Act when somebody RSVPs or changes their answer | [RSVP system](../rsvp/README.md#acting-on-an-rsvp) |
| Add your own RSVP status source or yes/no marker | [RSVP providers and flags](../rsvp/README.md) |
| Add a settings tab or fields | [Extending settings](../settings/extending-settings.md) |
| Add or remove blocks in the default event and venue layouts | [Hookable patterns](../blocks/hookable-patterns/README.md) |
| Add controls to the editor sidebar | [Slot fills](../blocks/slot-fills/README.md) |
| Override a template from a theme | [Theme customizations](../theme-customizations/README.md) |
| Add a URL endpoint such as a feed | [Custom URL endpoints](../custom-url-endpoints/README.md) |
| Carry your own data through import and export | [Content import and export](../content-import-export/README.md) |
| Add a WP-CLI command | [WP-CLI commands](../wp-cli/README.md) |
| Hook something else | [Hook reference](../hooks/) |

**If the extension point you need does not exist, that is worth raising.** A missing filter is a gap in GatherPress, and adding one so a companion plugin can do the work is usually a better outcome than building the feature into core. [Open an issue](https://github.com/GatherPress/gatherpress/issues).

## Conventions worth borrowing

These are core's conventions, and following them keeps a companion plugin predictable for anyone who has read GatherPress's source:

- **Gate on supports, not post types.** `post_type_supports( $post_type, 'gatherpress-event-date' )` rather than a comparison against `gatherpress_event`, so your plugin keeps working for people using their own post types.
- **Prefix everything** with your plugin's slug: options, meta keys, hook names, CSS classes.
- **Clean up on uninstall**, including across a multisite network. The scaffold shows the shape.
- **Follow the WordPress way**, for the same reasons GatherPress does.

## See also

- [Close to core](../philosophy/README.md), why companion plugins exist
- [Post type supports](../post-type-supports/README.md), the main extension surface
- [GatherPress Awesome](https://github.com/GatherPress/gatherpress-awesome), the starter plugin
