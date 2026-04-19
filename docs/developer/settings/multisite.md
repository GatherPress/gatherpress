# Multisite Network Settings

On a WordPress Multisite install, GatherPress can expose settings at the network level so super admins set one value that all sites in the network inherit. The inheritance is opt-in per option — individual sites remain free to manage the rest locally.

## UI Surfaces

Two separate surfaces render the Settings form:

- **Per-site** (unchanged) — `Dashboard → Events → Settings` on each site. Writes to the site's own `gatherpress_settings` blog option.
- **Network-wide** (new) — `Network Admin → Settings → GatherPress`. Renders the same Events / Venues / Roles / RSVP / Credits tabs plus an additional **Network** tab. Writes to the network-wide `gatherpress_settings` *site* option via `update_site_option()`. Requires the `manage_network_options` capability.

The Tools tab (import / export) stays per-site only — import / export is blog-scoped.

## The Network Tab

The Network tab at network admin holds the inheritance configuration. It is **not** a settings page in the usual sense; it writes to a separate site option `gatherpress_network_settings` whose shape is:

```php
array(
    'enabled'   => (bool) true,
    'inherited' => array( 'date_format', 'time_format', ... ),
)
```

When `enabled` is `false`, inheritance is off — every site reads its own blog option as usual. When `enabled` is `true`, any option key listed in `inherited` is resolved from the network-wide site option on subsites.

## Storage Layers

| Layer                                  | Stored As                                         | Used When                                                                 |
|----------------------------------------|---------------------------------------------------|---------------------------------------------------------------------------|
| Site / blog option `gatherpress_settings`             | `get_option` / `update_option`                    | Per-site values.                                                          |
| Network site option `gatherpress_settings`            | `get_site_option` / `update_site_option`          | Values set at Network Admin → Settings → GatherPress.                     |
| Network site option `gatherpress_network_settings`    | `get_site_option` / `update_site_option`          | Inheritance config — master toggle + list of inherited option keys.       |

The network admin UI reuses the existing `Settings` class: a `pre_option_gatherpress_settings` filter scoped to the network admin page (`load-{hook}`) short-circuits reads to the site option while that page renders, so the renderer is oblivious to which storage layer is in play.

## Resolver: `Settings::get()`

```php
use GatherPress\Core\Settings;

$date_format = Settings::get_instance()->get( 'date_format' );
```

Behavior:

1. On a **main site** or **single-site install**, returns the blog option value (or the flat default).
2. On a **subsite**, calls `Settings::is_option_inherited( 'date_format' )`. If `true`, returns the value from the network-wide site option (falling back to the flat default). If `false`, returns the blog option value.

## Per-Site Override Filter

`Settings::is_option_inherited()` runs its result through a filter so individual sites can opt out of inheritance for a given option. This is intentional as an escape hatch for edge cases — the default path is always "respect the network config."

```php
/**
 * Let site 42 manage its own date_format even though the network forces it.
 *
 * @param bool   $inherited Whether the option is inherited from the network.
 * @param string $option    The option key being resolved.
 * @param int    $blog_id   The current site ID.
 */
add_filter(
    'gatherpress_network_is_option_inherited',
    static function ( bool $inherited, string $option, int $blog_id ): bool {
        if ( 42 === $blog_id && 'date_format' === $option ) {
            return false;
        }
        return $inherited;
    },
    10,
    3
);
```

Returning `true` forces inheritance for an option that wasn't in the allowlist. Returning `false` exempts the current site.

## Subsite UX When an Option is Locked

- The field renders disabled + dimmed (`.gatherpress-field-inherited` wrapper + native `disabled` attribute on the `input` / `select`), and **shows the current network value** — not whatever the subsite had stored locally.
- A per-field note appears underneath. Super admins see `Inherited from the <a>network</a>. Edit there to change this value.`; regular site admins see a plain `Inherited from the network.` (no dead-end link).
- A page-level `notice-info` appears whenever any setting on the current page is inherited. Every admin sees the first sentence; only users with `manage_network_options` see the appended link sentence pointing to network admin.

## Capabilities

- Viewing and saving the Network Admin → Settings → GatherPress page requires `manage_network_options`. The save handlers (`network_admin_edit_gatherpress_network_settings` and `network_admin_edit_gatherpress_network_values`) re-check the capability and a per-action nonce.
- Per-site Settings pages remain gated by the existing `manage_options` capability.
