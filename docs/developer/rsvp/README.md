# RSVP

GatherPress stores RSVPs as WordPress comments with a custom `comment_type` of `gatherpress_rsvp`. The comment row is the canonical record: status changes (`attending`, `not_attending`, `waiting_list`), guest counts, and anonymous / authenticated state all live on the comment and its meta. Reusing the comments table means RSVPs inherit WordPress's moderation, capability, and i18n infrastructure for free.

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

The exclusion touches shared query vars, which can conflict with other plugins that read or write the same vars on `pre_get_comments`. The canonical example is the [ActivityPub plugin](https://github.com/Automattic/wordpress-activitypub): federation interactions (likes, boosts, quotes) flow through `wp_comments` with their own types, and ActivityPub's own `pre_get_comments` callback assumes the caller's `type__in` reflects the original query.

To opt out of GatherPress's default exclusion for a specific query, return
false from the `gatherpress_rsvp_comment_query_exclusion` filter. The filter
receives the live `WP_Comment_Query`, so the opt-out can be scoped to the
queries an integration actually owns:

```php
add_filter(
    'gatherpress_rsvp_comment_query_exclusion',
    function ( bool $exclude, WP_Comment_Query $query ): bool {
        // Only short-circuit when the caller is asking for federation
        // interaction types. Leave every other comment query alone so
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

Since 0.35.0 an RSVP response is attributed to a **provider**, the source of the responder's identity. GatherPress ships two: `user` (a logged-in WordPress account) and `email` (an address supplied through the open RSVP form). Companion plugins can add their own (a membership system, an external ticketing platform, an SSO directory), so responses from those sources are stored, displayed, and de-duplicated alongside the built-in ones.

### The pieces

- **`GatherPress\Core\Rsvp\Response\Identity`**: a value object pairing an
  `Identity_Type` with its value (a user ID, an email address, a URL, or an
  external ID). It validates on construction, so an invalid email or a
  non-existent user ID throws rather than persisting a bad row.
- **`GatherPress\Core\Rsvp\Response\Identity_Type`**: the enum of identity
  kinds: `WP_USER_ID`, `EMAIL`, `URL`, `EXTERNAL_ID`.
- **`GatherPress\Core\Rsvp\Response\Provider\Base`**: the abstract a provider
  extends. It declares what an identity *is* and how to present it; it does not
  touch storage (the repository owns that).
- **`GatherPress\Core\Rsvp\Response\Provider_Registry`**: the singleton that
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

On save, GatherPress stamps the provider's slug as a `_gatherpress_rsvp_provider` taxonomy term on the RSVP comment. That term is the authoritative record of which provider issued a response, and for a custom identity type such as `EXTERNAL_ID` it is the **only** way to resolve the provider later, since it can't be inferred from a user ID or email. For the two core providers the term is an optimization: the admin Type column and hydration both fall back to inferring `user` from a real user ID and `email` from a valid author email when no term is present, so responses written by paths that don't stamp it (the open RSVP form) still resolve.

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

Every flag fires `gatherpress_rsvp_flag_added` and `gatherpress_rsvp_flag_removed`
with the RSVP ID and the flag slug. To react to one flag, compare the slug with
that flag class's `SLUG`. Neither action fires on a repeat or a failed write,
and when two requests add the same flag at once, only the one that stored it
announces it.

```php
use My_Plugin\Flag\Walk_In;

function my_plugin_on_flag_added( int $rsvp_id, string $flag ): void {
	if ( Walk_In::SLUG === $flag ) {
		my_plugin_notify_door_staff( $rsvp_id );
	}
}
add_action( 'gatherpress_rsvp_flag_added', 'my_plugin_on_flag_added', 10, 2 );
```

The same pattern reacts to a check-in, with `Check_In::SLUG`.

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
the plugin (and in companion plugins following the same pattern) returns
false without needing its own setting check. The `gatherpress/rsvp*` blocks are
also filtered out of the block inserter, and the RSVPs admin page is not
registered.

Open RSVP has two gates: the sitewide `enable_open_rsvp` setting and a
per-event `gatherpress_enable_open_rsvp` post meta (unset means enabled).
`Rsvp::allows_open_rsvp()` resolves both; when it returns false the RSVP Form
block renders nothing and form or REST submissions are rejected with a 403.

## Custom fields and the form schema

An RSVP Form can carry fields beyond name and email: dietary needs, a t-shirt size, an accessibility request. Those are defined with Form Field blocks inside the form, and what a submission is validated against is not the blocks themselves but a **schema stored in post meta**.

### The schema

`gatherpress_rsvp_form_schemas` on the event post, keyed by form id:

```php
array(
    'form_0' => array(
        'fields' => array(
            'dietary' => array(
                'name'        => 'dietary',
                'type'        => 'text',
                'required'    => true,
                'label'       => 'Dietary needs',
                'placeholder' => '',
            ),
            'tshirt'  => array(
                'name'        => 'tshirt',
                'type'        => 'select',
                'required'    => false,
                'label'       => 'T-shirt size',
                'placeholder' => '',
                'options'     => array( 'S', 'M', 'L' ),
            ),
        ),
        'hash'   => '…',
    ),
)
```

Every field carries `name`, `type`, `required`, `label` and `placeholder`. Three keys are added by type: `options` for `select` and `radio`, `max_length` for `textarea`, and `validation` for `email`.

The form id is `form_<index>`, the index of the RSVP Form block in the post's top-level block list, and a nested form is prefixed with its parent's index. The `hash` is a hash of the field definitions, so a change to the fields changes it.

**This meta is the read surface.** Both submission paths and the REST handler resolve fields from it, not from the post content, so anything present in it is validated and stored like any other field.

### Supplying a schema from elsewhere

The block editor is the default producer, but it is not the only possible one. Group organizers who never open the editor still need registration questions, so a companion plugin may want to define the fields itself.

The obstacle used to be that saving a post asserted sole ownership of the meta: a post with no RSVP Form block produced an empty set and deleted whatever was stored. The `gatherpress_rsvp_form_schemas` filter runs on the derived set before it is written, so a consumer can keep its own:

```php
add_filter(
    'gatherpress_rsvp_form_schemas',
    function ( array $schemas, int $post_id ): array {
        $mine = my_plugin_get_registration_schema( $post_id );

        return $mine ? array_merge( $schemas, $mine ) : $schemas;
    },
    10,
    2
);
```

The meta is only deleted when the filtered result is still empty, so returning anything non-empty keeps it. Two things to know:

- **The filter runs on every save**, so a consumer has to return its schemas each time rather than writing the meta once and expecting it to persist.
- **Whether to merge or replace is yours.** Returning `$schemas` plus your own keeps an editor-composed form alongside yours. Returning only your own replaces it.

### How a submission is validated

Validation runs **before the RSVP is created**, on both the REST and traditional paths, so a submission that fails is rejected rather than stored with answers missing:

| Type | Accepted |
|---|---|
| `text` | Any string, sanitized |
| `email` | A valid address |
| `url` | A valid URL |
| `number` | Anything numeric |
| `select`, `radio` | A value present in `options` |
| `textarea` | Up to `max_length`, defaulting to 1000 |
| `checkbox` | Anything, stored as `1` or `0` |

A required field must be answered. Whitespace does not count, because it sanitizes to nothing. An explicit `"0"` does count, since it is a real option value.

Failures come back one per field. REST answers 400 with a summary `message` and an `errors` map keyed by field name; the traditional path stops the submission with the same messages. Nothing partial is stored and no confirmation email is sent.

### Reading the answers

Each answer is comment meta on the RSVP, prefixed to avoid collisions:

```php
get_comment_meta( $comment_id, 'gatherpress_custom_dietary', true );
```

A field the submitter left blank has no meta row, rather than an empty one.

## Acting on an RSVP

GatherPress fires no action of its own when somebody RSVPs or changes their
answer. It does not need to: an RSVP is a comment, so core's comment and
term hooks are the integration surface, and pushing a signup to a CRM or a
mailing list is a matter of listening to the right one.

Which one is the whole question, because a signup and a change are stored
differently.

### A new RSVP

A first response inserts a comment, so `wp_insert_comment` fires. Filter on
the comment type, since every comment on the site comes through here:

```php
use GatherPress\Core\Rsvp;

function my_plugin_on_rsvp_created( int $comment_id, WP_Comment $comment ): void {
	if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
		return;
	}

	my_plugin_push_to_crm( $comment_id );
}
add_action( 'wp_insert_comment', 'my_plugin_on_rsvp_created', 10, 2 );
```

**The RSVP has no status yet at this point.** The comment is inserted first
and the status term is written immediately afterwards
(`Rsvp\Storage::save()`), so anything reading the response inside this hook
gets nothing back. If you need to know what the person answered, use the
term hook below, which fires for a new RSVP as well.

### A change of answer

Moving from attending to not attending does **not** insert a comment. The
status is a term on the comment that already exists, so nothing in the
comment hooks fires at all. An integration built only on
`wp_insert_comment` records every signup and silently misses every
cancellation, which is the one failure a CRM sync cannot afford.

The status is written with `wp_set_object_terms()`, so core's
`set_object_terms` fires:

```php
use GatherPress\Core\Rsvp\Response\Status;

function my_plugin_on_rsvp_status( int $comment_id, $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
	if ( Status::TAXONOMY !== $taxonomy ) {
		return;
	}

	// Fires on every save, including one that stores the status the RSVP
	// already had, so compare before treating it as a change. Cast both
	// sides: core hands back the new IDs as strings and the old ones as
	// integers, so a strict comparison never matches.
	if ( array_map( 'intval', $tt_ids ) === array_map( 'intval', $old_tt_ids ) ) {
		return;
	}

	$status = (string) ( (array) $terms )[0];

	my_plugin_sync_to_crm( $comment_id, $status );
}
add_action( 'set_object_terms', 'my_plugin_on_rsvp_status', 10, 6 );
```

This one hook covers both events. The status term is written on the same
line whether the comment was just inserted or already existed, so a new RSVP
reaches it too, with `$old_tt_ids` empty.

### Which hook to use

| You need | Hook |
|---|---|
| Signups only, and nothing about the answer | `wp_insert_comment`, filtered to `Rsvp::COMMENT_TYPE` |
| The answer, or cancellations, or both | `set_object_terms`, filtered to `Status::TAXONOMY` |

Reach for the second unless you are certain you only care that a comment
appeared. Starting with the first and adding the second later means going
back through whatever you already synced.

### The statuses

`attending`, `not_attending` and `waiting_list`, plus `no_status` for a
response that has none. They are the cases of the `Rsvp\Response\Status`
enum, so compare against `Status::ATTENDING->value` rather than retyping the
string.

A waiting-list entry becoming `attending` because a place opened up arrives
as an ordinary status change, indistinguishable from the person changing
their own answer. If that distinction matters, read the flags on the RSVP.

### Reading the rest of the response

The comment ID is the RSVP ID everywhere else in this document. Guest counts
and anonymous state live in comment meta, the identity source is a term
(see [RSVP providers](#rsvp-providers-identity-sources)), and yes/no markers
are flags (see [RSVP flags](#rsvp-flags)). Querying alongside ordinary
comments has its own rules, covered in
[Comment-query coexistence](#comment-query-coexistence).

## Further reading

- Per-hook reference: [`docs/developer/hooks/`](../hooks/) (auto-regenerated by
  CI from the docblock above each `apply_filters()` / `do_action()` call).
- Hook naming conventions:
  [`docs/developer/hooks-naming-convention.md`](../hooks-naming-convention.md).
