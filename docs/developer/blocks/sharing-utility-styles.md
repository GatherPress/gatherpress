# Sharing the GatherPress utility stylesheet with your own blocks

GatherPress ships a small shared utility stylesheet (`gatherpress-utility-style`,
sourced from `build/utility_style.css`) used by GatherPress's own blocks for
things like the `gatherpress--is-hidden` visibility helper that drives RSVP
status switching, tooltip variables, and other primitives shared across the
block library.

On the frontend, GatherPress only enqueues the utility stylesheet when a block
whose name starts with `gatherpress/` is actually rendered, so pages without
any GatherPress block don't pay for the CSS.

Companion plugins and themes can opt their own blocks into the same coverage
by adding their block-name prefix to the
`gatherpress_asset_utility_style_block_prefixes` filter.

## When to use this filter

Use this filter when your block:

- Renders markup that relies on classes defined in `utility_style.css` (e.g.
  `gatherpress--is-hidden`), and
- You'd rather inherit the host plugin's CSS than maintain your own copy that
  could drift if GatherPress changes a class name.

If your block doesn't use any of those classes, you don't need this filter —
WordPress will load only the styles your own block registers.

## How it works

GatherPress always handles the `gatherpress/` prefix itself. The filter is
purely additive: whatever array you return is merged with `gatherpress/`
before the prefix match runs, so you cannot accidentally break GatherPress's
own blocks by replacing the array — you can only add to it.

## Example

A companion plugin that ships blocks under the `gatherpress-awesome/`
namespace can opt them all in with one filter callback:

```php
add_filter(
    'gatherpress_asset_utility_style_block_prefixes',
    static function ( array $prefixes ): array {
        $prefixes[] = 'gatherpress-awesome/';
        return $prefixes;
    }
);
```

After this runs, any block whose `blockName` begins with `gatherpress-awesome/`
will cause `gatherpress-utility-style` to be enqueued on the page when that
block is rendered, alongside GatherPress's own blocks.

You can add multiple prefixes at once:

```php
add_filter(
    'gatherpress_asset_utility_style_block_prefixes',
    static function ( array $prefixes ): array {
        $prefixes[] = 'gatherpress-awesome/';
        $prefixes[] = 'gatherpress-productions/';
        return $prefixes;
    }
);
```

## Caveats

- The filter is matched as a **prefix** (via `str_starts_with`). Pass the
  trailing slash (`my-plugin/`) so it doesn't accidentally match unrelated
  namespaces like `my-plugin-extra/`.
- The utility stylesheet is registered under the handle
  `gatherpress-utility-style`. If you need it loaded in a non-block context
  (e.g. a settings screen of your own), enqueue it directly by handle.
- Frontend enqueue is gated on `render_block` for prefixes returned by the
  filter. Blocks rendered exclusively client-side (without ever hitting
  PHP `render_block`) won't trigger the enqueue — fall back to enqueuing
  the handle yourself in that case.
