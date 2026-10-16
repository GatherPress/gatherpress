# Demo data and prepared playgrounds

A prepared WordPress with GatherPress installed and real-looking events already in it, reachable from a link, with no setup. It is how the plugin is demoed, how pull requests are reviewed, how the WordPress.org screenshots are generated, and often the quickest way to reproduce a bug.

Everything here is built from two pieces: a **blueprint** that says how to boot WordPress, and a **demo data** WXR file that fills it with content.

## The blueprints

[WordPress Playground](https://developer.wordpress.org/playground/) runs WordPress in the browser. A blueprint is a JSON recipe telling it what to install and what to do first. GatherPress keeps two, in [`.wordpress-org/blueprints/`](../../../.wordpress-org/blueprints/):

| Blueprint | Installs | For |
|---|---|---|
| `blueprint.json` | The current release from WordPress.org | Showing people the plugin |
| `blueprint-nightly.json` | The latest nightly build | Trying what is on `develop` |

Both do the same setup: name the site, switch on pretty permalinks, create an `editor` user alongside `admin` so permission differences can be seen, log in, install and activate GatherPress, and import the demo content.

**Launch the stable one**: [Playground with GatherPress](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/GatherPress/gatherpress/main/.wordpress-org/blueprints/blueprint.json)

### The import shim

Both blueprints write a small must-use plugin before importing, `gatherpress-do-not-rewrite-urls-on-import.php`.

WordPress's importer rewrites URLs in imported content to match the destination site. That is right for a migration and wrong here, because Playground's origin changes on every boot and the rewritten URLs would point at whatever the last session happened to be. The shim turns that off for the import.

It is a good thing to know about before you conclude a URL-related bug is in GatherPress.

## The demo data

The content lives in its own repository, [gatherpress-demo-data](https://github.com/GatherPress/gatherpress-demo-data), as a WXR file named for the GatherPress version it targets, for example `GatherPress-demo-data-0.35.0.xml`.

It is versioned that way on purpose. Demo content carries block markup, and block markup changes between releases, so a file exported against an older version can produce blocks the current release renders differently. When you bump the file the blueprints point at, check it still renders.

### Adding your own events

The demo data is a contribution anyone can make, and one of the more visible ones: every Playground preview, every screenshot and every demo anybody opens is showing this content. Real meetups make a far better demo than placeholder text.

The repository's own README covers how to add yours.

## Where they are used

**Pull request previews.** Every pull request gets a Playground link with that branch built and installed, seeded with the same demo data, so anyone can click through a change without a local checkout. Previews can also be customized per pull request when a change needs particular setup. See [Playground PR previews](../../contributor/playground-pr-preview/README.md).

**Screenshots for WordPress.org.** The screenshot generator boots a Playground from the same demo data and drives it with Playwright, in every locale. Consistent content is what makes those screenshots reproducible. See [screenshot generator](../../contributor/screenshot-generator/README.md).

**Bug reports.** A Playground link showing the bug is worth more than a description of it, and costs the reporter nothing.

## Using one yourself

For local development, `wp-env` is the better tool, and [the developer setup](../README.md) covers it. Playground is for the cases `wp-env` is awkward for:

- Showing somebody the plugin when they will not install anything
- Checking a branch on a machine with no checkout, including a phone
- Reproducing a bug from a clean install in a known state
- Giving a reviewer something to click

To boot your own variation, point Playground at a blueprint URL:

```text
https://playground.wordpress.net/?blueprint-url=<url-to-your-blueprint.json>
```

Copy one of GatherPress's as a starting point. The [Playground blueprint gallery](https://github.com/WordPress/blueprints) has more examples, and the [builder](https://playground.wordpress.net/builder/builder.html) will assemble one interactively.

> [!NOTE]
> A Playground is temporary and lives in your browser. Closing the tab discards everything unless you have explicitly saved it. Never put anything you need to keep in one.

## See also

- [Playground](../../playground.md), the user-facing introduction
- [Playground PR previews](../../contributor/playground-pr-preview/README.md)
- [Screenshot generator](../../contributor/screenshot-generator/README.md)
- [Content import and export](../content-import-export/README.md), how the WXR carries event dates
