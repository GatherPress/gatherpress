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
`pre_get_comments` and removes the `gatherpress_rsvp` type from each query's
`type` / `type__in` vars in `Rsvp\Query::exclude_rsvp_from_comment_query()`
([`includes/core/classes/rsvp/class-query.php`](../../../includes/core/classes/rsvp/class-query.php)).

The exclusion mutates query vars rather than appending a `WHERE comment_type !=
…` clause because the `comment_type` column is not indexed in WordPress core
(see [Trac #59488](https://core.trac.wordpress.org/ticket/59488)). Pre-populating
the type list lets MySQL use the existing index and avoids a full-table scan on
sites with large comment volumes.

### When the default exclusion gets in the way

Mutating shared query vars is the right trade-off for performance but can
conflict with other plugins that read or write the same vars on
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
