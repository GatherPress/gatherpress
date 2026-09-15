# RSVP

GatherPress stores RSVPs as WordPress comments with a custom `comment_type` of
`gatherpress_rsvp`. The comment row is the canonical record — status changes
(`attending`, `not_attending`, `waiting_list`), guest counts, and anonymous /
authenticated state all live on the comment and its meta. Reusing the comments
table means RSVPs inherit WordPress's moderation, capability, and i18n
infrastructure for free.

## Comment-query coexistence

Because RSVPs live in `wp_comments`, generic comment queries (sidebar widgets,
admin moderation lists, REST endpoints, federation plugins) would surface them
alongside real comments unless filtered out. GatherPress hooks
`pre_get_comments` in `Rsvp\Query::exclude_rsvp_from_comment_query()`
([`includes/core/classes/rsvp/class-query.php`](../../../includes/core/classes/rsvp/class-query.php)),
which adds the `gatherpress_rsvp` type to `type__not_in` and leaves `type` /
`type__in` as the caller wrote them. Core turns that into
`comment_type NOT IN ('gatherpress_rsvp')` on top of any allow-list, so the
exclusion holds whatever else is stored: a site whose only comments are RSVPs,
a caller asking for `all`, and a query that only sets `type__in` all stay
RSVP-free, and an allow-list naming only the RSVP type returns nothing while
the exclusion is active.

The `comment_type` column is not indexed in WordPress core (see
[Trac #59488](https://core.trac.wordpress.org/ticket/59488)), so the type
condition is evaluated per row after the post ID or approval index has
narrowed the query. Sites with large comment volumes can add a `comment_type`
index themselves; once one exists, whether core adds it or the site does, the
`NOT IN` becomes a range scan on that index.

### When the default exclusion gets in the way

The exclusion touches shared query vars, which can conflict with other plugins
that read or write the same vars on
`pre_get_comments`. The canonical example is the [ActivityPub
plugin](https://github.com/Automattic/wordpress-activitypub) — federation
interactions (likes, boosts, quotes) flow through `wp_comments` with their own
types, and ActivityPub's own `pre_get_comments` callback assumes the caller's
`type__in` reflects the original query.

To opt out of GatherPress's default exclusion for a specific query, return
false from the `gatherpress_rsvp_comment_query_exclusion` filter. The filter
receives the live `WP_Comment_Query`, so the opt-out can be scoped to the
queries an integration actually owns:

```php
add_filter(
    'gatherpress_rsvp_comment_query_exclusion',
    function ( bool $exclude, WP_Comment_Query $query ): bool {
        // Only short-circuit when the caller is asking for federation
        // interaction types — leave every other comment query alone so
        // GatherPress's default RSVP exclusion continues to apply.
        $types = (array) ( $query->query_vars['type__in'] ?? array() );

        if ( array_intersect( $types, array( 'like', 'announce' ) ) ) {
            return false;
        }

        return $exclude;
    },
    10,
    2
);
```

A scoped opt-out is preferred over a global `remove_action()` against
`Rsvp\Query::exclude_rsvp_from_comment_query`: it leaves the exclusion in
effect for unrelated comment lists on the same site, and it has no coupling to
GatherPress's class names or singleton accessors, so it survives internal
refactors without code changes on the integration side.

## RSVP providers (identity sources)

Since 0.35.0 an RSVP response is attributed to a **provider** — the source of
the responder's identity. GatherPress ships two: `user` (a logged-in WordPress
account) and `email` (an address supplied through the open RSVP form). Companion
plugins can add their own — a membership system, an external ticketing platform,
an SSO directory — so responses from those sources are stored, displayed, and
de-duplicated alongside the built-in ones.

### The pieces

- **`GatherPress\Core\Rsvp\Response\Identity`** — a value object pairing an
  `Identity_Type` with its value (a user ID, an email address, a URL, or an
  external ID). It validates on construction, so an invalid email or a
  non-existent user ID throws rather than persisting a bad row.
- **`GatherPress\Core\Rsvp\Response\Identity_Type`** — the enum of identity
  kinds: `WP_USER_ID`, `EMAIL`, `URL`, `EXTERNAL_ID`.
- **`GatherPress\Core\Rsvp\Response\Provider\Base`** — the abstract a provider
  extends. It declares what an identity *is* and how to present it; it does not
  touch storage (the repository owns that).
- **`GatherPress\Core\Rsvp\Response\Provider_Registry`** — the singleton that
  holds registered providers and fires the registration hook.

### The provider contract

A provider extends `Base` and implements four abstract methods:

| Method | Returns | Purpose |
|---|---|---|
| `get_slug()` (static) | `string` | Stable identifier, **4+ characters**. Stored as the provider taxonomy term and used as the registry key. |
| `get_identity_type()` (static) | `Identity_Type` | Which identity kind this provider issues. |
| `get_label()` (static) | `string` | Human-readable name shown in the RSVPs admin Type column. |
| `get_display_name( Identity $identity )` | `string` | The best name to show for a given identity (see the WordPress-style "display name" note on `Base`). |

`Base` also provides two overridable helpers with sensible defaults:
`get_avatar_url( Identity $identity )` and `get_url( Identity $identity )`
(profile link), each returning `?string`.

### Registering a provider

Hook `gatherpress_register_rsvp_types` and call `register()` with an instance.
The action fires on `gatherpress_loaded` after the core providers register, so a
plugin loaded normally is in time. `register()` returns `false` for a duplicate
slug and throws `InvalidArgumentException` for a slug shorter than four
characters.

```php
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Provider\Base;

final class Membership_Provider extends Base {

	public static function get_slug(): string {
		return 'membership';
	}

	public static function get_identity_type(): Identity_Type {
		return Identity_Type::EXTERNAL_ID;
	}

	public static function get_label(): string {
		return __( 'Member', 'my-plugin' );
	}

	public function get_display_name( Identity $identity ): string {
		$member = my_plugin_get_member( (int) $identity->value );

		return $member ? $member->name : '';
	}
}

add_action( 'gatherpress_register_rsvp_types', function ( $registry ) {
	$registry->register( new Membership_Provider() );
} );
```

### What the provider term is (and isn't) for

On save, GatherPress stamps the provider's slug as a `_gatherpress_rsvp_provider`
taxonomy term on the RSVP comment. That term is the authoritative record of which
provider issued a response — and for a custom identity type such as
`EXTERNAL_ID` it is the **only** way to resolve the provider later, since it
can't be inferred from a user ID or email. For the two core providers the term
is an optimization: the admin Type column and hydration both fall back to
inferring `user` from a real user ID and `email` from a valid author email when
no term is present, so responses written by paths that don't stamp it (the open
RSVP form) still resolve.

## RSVP flags

Since 0.36.0 an RSVP can carry **flags**: yes/no markers such as "this person
checked in" or "this person walked in without an RSVP". A flag is a term in the
`_gatherpress_rsvp_flag` taxonomy attached to the RSVP comment. Its presence
means yes and its absence means no, so existing RSVPs need no backfill.

Each flag is a class that extends `GatherPress\Core\Rsvp\Flag\Base` and
declares its slug, much as a settings tab extends `GatherPress\Core\Settings\Base`.
An instance wraps one RSVP, the way `new Token( $comment_id )` does. GatherPress
and companion plugins share the one taxonomy, so anything that needs yes/no
state on an RSVP should define a flag rather than register a taxonomy of its
own.

### The flag classes

- **`GatherPress\Core\Rsvp\Flag\Base`**: the abstract class a flag extends. It
  stores, reads, and counts the flag, and fires the flag hooks.
- **`GatherPress\Core\Rsvp\Flag\Check_In`**: the `checked-in` flag, the first
  one GatherPress ships.
- **`GatherPress\Core\Rsvp\Flag\Setup`**: what belongs to every flag rather
  than one. It registers the taxonomy, reads all flags on an RSVP, and sweeps
  them when the RSVP is deleted.

### Adding a flag

Create a class that extends `Base` and declares the slug in its `SLUG`
constant:

```php
<?php

namespace My_Plugin\Flag;

use GatherPress\Core\Rsvp\Flag\Base;

class Walk_In extends Base {
	public const SLUG = 'my-plugin-walk-in';
}
```

That is the whole flag. It has no hooks of its own, so it needs no
bootstrapping. Build one around an RSVP wherever you need it:

```php
use My_Plugin\Flag\Walk_In;

$walk_in = new Walk_In( $rsvp_id );

$walk_in->add();    // true once the RSVP carries the flag.
$walk_in->has();    // true.
$walk_in->remove(); // true once the RSVP no longer carries it.

Walk_In::count( $event_id ); // Approved RSVPs on the event with the flag.
```

### The methods

| Method | Returns | Purpose |
|---|---|---|
| `new Flag( int $rsvp_id )` | | Wraps one RSVP. An ID that is not an RSVP comment gives a flag that refuses every write. |
| `add()` | `bool` | Adds the flag, leaving every other flag on the RSVP in place. |
| `remove()` | `bool` | Removes the flag, leaving every other flag in place. |
| `has()` | `bool` | Whether the RSVP carries the flag. |
| `Flag::count( int $post_id )` | `int` | How many approved RSVPs on the event carry the flag. Static, since it spans an event. |

`add()` and `remove()` are idempotent. They return `true` when the RSVP ends up
in the requested state, including when it was already there, and `false` when
the comment is not an RSVP, the slug is invalid, the write failed, or the
taxonomy is not registered yet. It registers on `init`, so write flags from
`init` or later.

`count()` includes approved RSVPs only, matching the attendee list: a held or
spammed RSVP is not part of the event's audience even if it was flagged before
it was moderated.

To read every flag on an RSVP at once, use
`Setup::get_instance()->get_flags( $rsvp_id )`. It returns the slugs and reads
through the object term cache, so checking several flags on one RSVP queries
once.

### Reacting to a change

Override `after_add()` or `after_remove()` to run code when your flag actually
changes. Neither runs on a repeat or a failed write. The RSVP is available as
`$this->rsvp_id`. `Check_In` uses them to fire its own actions:

```php
protected function after_add(): void {
	do_action( 'my_plugin_walk_in_recorded', $this->rsvp_id );
}
```

### Slugs

A slug needs no registration, so a companion plugin can add one without
coordinating with GatherPress. It must already be in the shape `sanitize_key()`
produces: lowercase letters, digits, hyphens and underscores. `walk-in` and
`first_timer` are accepted; `Walk In` and `HOST` are refused rather than
silently rewritten, so the flag stored is always the one the class declares. A
class that leaves `SLUG` empty is refused too.

Prefix slugs your plugin owns, for example `my-plugin-vip`, to stay clear of
any flag GatherPress adds later.

### Write through a flag class

Never call `wp_set_object_terms()` on `_gatherpress_rsvp_flag` directly. Without
`$append = true` it replaces every flag on the RSVP, wiping state other code
stored there. `add()` always appends.

Reads have a similar trap: `is_object_in_term()` matches term names as well as
slugs, and treats a numeric string as a term ID. `has()` compares slugs only.

### Hooks

| Action | Arguments | Fires |
|---|---|---|
| `gatherpress_rsvp_flag_added` | `int $rsvp_id`, `string $flag` | After any flag is added to an RSVP that did not carry it. |
| `gatherpress_rsvp_flag_removed` | `int $rsvp_id`, `string $flag` | After any flag is removed from an RSVP that carried it. |
| `gatherpress_rsvp_checked_in` | `int $rsvp_id` | After an RSVP is checked in. |
| `gatherpress_rsvp_unchecked_in` | `int $rsvp_id` | After an RSVP's check-in is removed. |

None fires when the RSVP was already in the requested state, or when the write
failed. Deleting an RSVP sweeps every flag on it without firing the removal
actions, since the RSVP itself is gone.

### What belongs in a flag

- **Yes/no state only.** Anything carrying a value, such as a guest count or a
  timestamp, belongs in comment meta.
- **Not one-of-N choices.** RSVP status and provider are dimensions, each with
  its own taxonomy, and should not be folded in.
- **Nothing private.** `public` and `show_in_rest` apply to the whole taxonomy,
  so a flag that must not be exposed, such as a moderation note, needs its own
  taxonomy.

## Sitewide gating (RSVP Mode and Open RSVP)

Since 0.34.0 the `rsvp_mode` setting is the master switch for the whole RSVP
subsystem. When it is set to `disabled`, GatherPress removes the
`gatherpress-rsvp` post type support from every post type that declares it
(`Rsvp\Setup::maybe_disable_rsvp()`), so every `post_type_supports()` guard in
the plugin — and in companion plugins following the same pattern — returns
false without needing its own setting check. The `gatherpress/rsvp*` blocks are
also filtered out of the block inserter, and the RSVPs admin page is not
registered.

Open RSVP has two gates: the sitewide `enable_open_rsvp` setting and a
per-event `gatherpress_enable_open_rsvp` post meta (unset means enabled).
`Rsvp::allows_open_rsvp()` resolves both; when it returns false the RSVP Form
block renders nothing and form or REST submissions are rejected with a 403.

## Further reading

- Per-hook reference: [`docs/developer/hooks/`](../hooks/) (auto-regenerated by
  CI from the docblock above each `apply_filters()` / `do_action()` call).
- Hook naming conventions:
  [`docs/developer/hooks-naming-convention.md`](../hooks-naming-convention.md).
