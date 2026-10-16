# Importing and exporting events, venues and topics

GatherPress does not ship an importer. It teaches **WordPress's own** import and export tools about the data that does not live in post meta, so Tools → Export and Tools → Import work on events the way they already work on posts.

> [!NOTE]
> This is about *content*. Exporting the plugin's **settings** is a separate thing with its own file format and WP-CLI commands. See [settings import and export](../settings/README.md).

## What round-trips, and what does not

Everything WordPress already handles comes along for free, because events and venues are ordinary post types:

- Event and venue posts, with their titles, content, status, slug and dates
- Topics and the hidden venue taxonomy, as term assignments
- All the post meta listed in the [data model](../data-model/README.md): RSVP limits, the online-event link, venue address and coordinates

**RSVPs do not.** They are comments, and WordPress's exporter does not carry comments for custom post types in a form GatherPress can restore. An imported event arrives with no responses.

The interesting case is the one in between: an event's dates.

## Why dates need special handling

Event dates live in the [`gatherpress_events` table](../data-model/README.md#the-events-table), not in post meta. WXR, the XML format WordPress exports, has no concept of a custom table. A plain export would carry the event and lose its date, which is the one thing an event cannot be without.

So GatherPress writes the dates into the export **as if** they were post meta, under a key that does not exist in the database: `gatherpress_datetimes`. On import it intercepts that key before WordPress can store it, unpacks it, and writes the real row into the events table. Nothing named `gatherpress_datetimes` is ever stored on either side.

Internally these are called *pseudo post metas*: a name, an export callback that produces a value, and an import callback that consumes one.

## How it hangs together

**Exporting** (`Core\Export`):

1. On `export_wp`, GatherPress hooks into the export that is starting.
2. On `the_post`, each event gets a temporary real meta marker, `gatherpress_extend_export`.
3. The `wxr_export_skip_postmeta` filter fires for that marker. GatherPress uses it as its entry point, echoes a `<wp:postmeta>` element for every registered pseudo post meta, and then returns `false` so the marker itself is skipped.

The marker exists because WordPress's exporter has no per-post action to hook. Writing a meta key purely to get called back is a workaround, and the code says so plainly.

**Importing** (`Core\Import`):

1. GatherPress hooks whichever importer is installed: `wxr_importer.pre_process.post` for the v2 importer, `wp_import_post_data_raw` for the classic one.
2. Posts whose type declares `gatherpress-event-date` fire the `gatherpress_import` action.
3. That adds a short-circuiting `add_post_metadata` filter. When a pseudo-post-meta key comes past, its import callback runs and the filter returns early, so WordPress never writes the fake key to `wp_postmeta`.

Both sides gate on **post type support**, not on the `gatherpress_event` slug. A companion plugin's own event post type round-trips its dates too, as long as it declares `gatherpress-event-date`.

## Carrying your own data

Register a pseudo post meta with the `gatherpress_pseudo_post_metas` filter and your data travels in the same WXR file, through the same mechanism:

```php
add_filter(
	'gatherpress_pseudo_post_metas',
	function ( array $pseudopostmetas ): array {
		$pseudopostmetas['my_plugin_seating_chart'] = array(
			'export_callback' => function ( WP_Post $post ): string {
				return maybe_serialize( my_plugin_get_seating_chart( $post->ID ) );
			},
			'import_callback' => function ( int $post_id, $meta_value ): void {
				my_plugin_save_seating_chart( $post_id, maybe_unserialize( $meta_value ) );
			},
		);

		return $pseudopostmetas;
	}
);
```

- **`export_callback( WP_Post $post ): string`** returns the value to write. It has to be a string, so serialize anything structured. It runs inside the export request, so keep it cheap.
- **`import_callback( int $post_id, $meta_value ): void`** stores it wherever it belongs. Returning nothing is the point, because the filter has already stopped WordPress from writing the key itself.

The default registration is a good model, because it is the same shape:

```php
protected array $pseudopostmetas = array(
	'gatherpress_datetimes' => array(
		'export_callback' => array( Export::class, 'datetimes_callback' ),
		'import_callback' => array( Import::class, 'datetimes_callback' ),
	),
);
```

Two things to watch:

- **Pick a key nothing will collide with.** It shares a namespace with real post meta for the length of the import, so prefix it with your plugin.
- **The export callback must not leak the current user.** `Export::datetimes_callback()` calls `remove_all_filters( 'gatherpress_timezone' )` before reading the event, because the site's export should not be shaped by whichever administrator happened to run it.

## Migrating from another events plugin

The same filter is the migration path. An importer for another plugin's format registers an `import_callback` for the key that plugin's export uses, and writes the values into GatherPress's own storage. Nothing has to touch GatherPress's tables directly, and nothing has to run after the import as a repair pass.

## See also

- [Data model](../data-model/README.md): what lives in meta, what lives in the table
- [Settings import and export](../settings/README.md): the separate, settings-only mechanism
- [Post type supports](../post-type-supports/README.md): why both sides gate on support rather than post type
