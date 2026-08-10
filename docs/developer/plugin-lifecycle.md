# Plugin lifecycle and the `gatherpress_loaded` hook

When WordPress loads GatherPress, the main plugin file (`gatherpress.php`) runs
a short, fixed sequence:

1. Define the plugin constants (`GATHERPRESS_CORE_PATH`, `GATHERPRESS_VERSION`,
   and so on).
2. Run the duplicate-plugin and minimum-requirements checks, bailing early if
   either fails.
3. Register the class autoloader and the legacy class aliases.
4. Instantiate the plugin: `GatherPress\Core\Setup::get_instance()`. This
   constructs every subsystem (events, venues, RSVP, settings, …), and each
   subsystem wires its own hooks in its constructor.
5. Schedule the `gatherpress_loaded` action to fire on `plugins_loaded`.

## `gatherpress_loaded`

*Available since GatherPress 0.34.1.*

```php
add_action( 'gatherpress_loaded', function () {
    // Every core GatherPress class exists — safe to integrate.
} );
```

Fires once, on `plugins_loaded`, to signal that **every core GatherPress class
has been instantiated**. Code that needs a GatherPress class to already exist —
a registry to register into, a singleton to read — can run here instead of
guessing at load order.

Crucially, it fires **only after the requirements check has passed**. If
GatherPress bails early — the site is below the minimum PHP or WordPress
version, or the build is missing — the action never fires. That makes it the
correct signal for anything that must not run when GatherPress isn't fully
loaded (see [Companion plugins](#companion-plugins) below).

The classes are actually constructed earlier, at step 4 (plugin include time).
The action is deliberately deferred to `plugins_loaded` so that it fires *after
every active plugin's main file has been included* — which means a listener
added at the top level of **any** plugin is registered in time to catch it,
regardless of whether that plugin loads before or after GatherPress. Without the
deferral, a plugin that loaded after GatherPress would miss the event.

GatherPress uses it internally: the RSVP provider registry
(`Rsvp\Response\Provider_Registry`) hooks `gatherpress_loaded` and, when it
fires, dispatches its own `gatherpress_register_rsvp_types` action so companion
plugins can register custom providers once the registry is ready. See the
[RSVP developer guide](rsvp/README.md#rsvp-providers-identity-sources).

## Which hook should I use?

- **To integrate with GatherPress-specific extension points** — register an RSVP
  provider, react to the subsystems being ready — hook **`gatherpress_loaded`**.
- **If you only need GatherPress classes to *exist*** (call a singleton, read a
  setting, query events) and don't need the readiness event itself, any hook
  from `plugins_loaded` onward (including `init`) works, since GatherPress is
  fully instantiated by then. Guarding with `class_exists()` / `function_exists()`
  is still good practice for a soft dependency.
- **If you are a companion plugin whose classes load through GatherPress's
  autoloader**, you must boot on `gatherpress_loaded` — see below.

## Companion plugins

A companion plugin (GatherPress Alpha, GatherPress Awesome, and the like)
registers its namespace with GatherPress's autoloader through the
`gatherpress_autoloader` filter, then instantiates its own `Setup` once
GatherPress is ready:

```php
// Register the namespace with GatherPress's autoloader (top level, so it is
// in place before GatherPress instantiates).
add_filter( 'gatherpress_autoloader', function ( array $namespaces ): array {
    $namespaces['GatherPress_Awesome'] = __DIR__;

    return $namespaces;
} );

// Boot once GatherPress has fully loaded.
add_action( 'gatherpress_loaded', function (): void {
    GatherPress_Awesome\Setup::get_instance();
} );
```

**Boot on `gatherpress_loaded`, never on the `GATHERPRESS_VERSION` constant and
never on `plugins_loaded` alone.** The constant is defined *before* the
requirements check, so `defined( 'GATHERPRESS_VERSION' )` means "GatherPress
began loading", not "GatherPress loaded successfully". If GatherPress bails at
its requirements gate, the constant is set but the autoloader was never
registered — so a companion that boots on the constant calls into its own
classes, which cannot be autoloaded, and the whole site fatals with a white
screen instead of GatherPress's "please upgrade" notice. Gating on
`gatherpress_loaded` avoids this: the action simply never fires, and the
companion stays dormant.

Two details matter:

- **Register the listener at the top level of your main file**, not inside a
  `plugins_loaded` callback. GatherPress dispatches `gatherpress_loaded` from
  within its own `plugins_loaded` callback, which is registered first, so a
  listener you add inside yours would be attached after the action had already
  fired.
- **Notices that explain why your plugin is dormant** ("GatherPress is not
  installed", a version-mismatch warning) belong on `plugins_loaded`, because
  they must run in exactly the cases where `gatherpress_loaded` never fires.

### The coexistence guard

The same rule applies to registering with GatherPress's coexistence guard.
GatherPress's `Coexistence_Guard` listens for the
`gatherpress_register_coexistence_guard` action, and that listener only exists
once GatherPress has bootstrapped — so announce your plugin on
`gatherpress_loaded`:

```php
add_action( 'gatherpress_loaded', function (): void {
    do_action(
        'gatherpress_register_coexistence_guard',
        'gatherpress-awesome',      // Folder slug; main file must be <slug>.php.
        'GatherPress Awesome',      // Display name.
        __FILE__                    // Absolute path to your main plugin file.
    );
} );
```

## See also

- [RSVP providers](rsvp/README.md#rsvp-providers-identity-sources) — the main
  consumer of `gatherpress_loaded`.
- The auto-generated per-hook reference under
  [`docs/developer/hooks/`](hooks/) (regenerated by CI on merge).
