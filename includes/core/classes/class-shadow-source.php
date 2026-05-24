<?php
/**
 * Per-post-type shadow taxonomy lifecycle.
 *
 * Registers a hidden `_<post_type>` taxonomy for any post type that declares
 * the `gatherpress-shadow-source` support, and wires the save/update/delete
 * hooks that keep one term per post in lockstep with the post slug. The
 * `gatherpress_venue` post type uses this primitive to power the venue
 * taxonomy that tags events; companion plugins can declare the same support
 * on their own post types (e.g. productions, organizers) to get the same
 * shadow-taxonomy behavior without depending on any venue-specific code.
 *
 * Wiring the resulting taxonomy onto consumer post types (events, sessions,
 * etc.) is the developer's responsibility — pass it via `register_post_type`'s
 * `taxonomies` arg or call `register_taxonomy_for_object_type()`. The venue
 * subsystem performs that wiring for `gatherpress-venue` post types so
 * the venue ⇄ event relationship continues to work out of the box.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Post;
use WP_Post_Type;
use WP_Term;

/**
 * Class Shadow_Source.
 *
 * Generic shadow-taxonomy primitive shared by the venue subsystem and any
 * companion plugin that declares `gatherpress-shadow-source` on its own
 * post type. Owns the per-post-type taxonomy registration and the term
 * lifecycle (insert / rename / delete in lockstep with the source post).
 *
 * @since 0.34.0
 */
class Shadow_Source {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 0.34.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for taxonomy registration and term lifecycle.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'maybe_register_post_type_hooks' ) );
		// Priority 11 so post types registered at default priority 10 are
		// available for get_post_types_by_support().
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		// Priority 12 runs after our own register_taxonomies (priority 11) so
		// each shadow taxonomy exists before we ask which post types should
		// carry it.
		add_action( 'init', array( $this, 'attach_taxonomies_to_object_types' ), 12 );
		// post_updated has no per-type variant in WP core, and we need
		// $post_before for the old/new post_name diff, so this stays on the
		// global hook.
		add_action( 'post_updated', array( $this, 'maybe_update_term_slug' ), 10, 3 );
	}

	/**
	 * Wire per-post-type lifecycle hooks when a shadow-source post type registers.
	 *
	 * Registration is gated on `gatherpress-shadow-source` support so any post
	 * type that declares it — including companion-plugin post types —
	 * automatically gets the term wired to its save and delete lifecycle,
	 * without needing to hook the site-wide `save_post`/`delete_post` actions.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function maybe_register_post_type_hooks( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-shadow-source' ) ) {
			return;
		}

		add_action(
			sprintf( 'save_post_%s', $post_type ),
			array( $this, 'add_term' ),
			10,
			3
		);
		add_action(
			sprintf( 'delete_post_%s', $post_type ),
			array( $this, 'delete_term' )
		);
	}

	/**
	 * Register one hidden taxonomy per shadow-source post type.
	 *
	 * The taxonomy slug is `_<post_type>` and term slugs are derived from the
	 * post's `post_name` via {@see self::term_slug_from_post_name()}. The
	 * leading underscore keeps the slugs uneditable through the WordPress UI
	 * and signals that they're programmatically managed.
	 *
	 * Labels are inherited from the source post type so admin columns and
	 * Query Loop taxonomy controls read naturally for any consumer.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		foreach ( get_post_types_by_support( 'gatherpress-shadow-source' ) as $post_type ) {
			register_taxonomy(
				$this->get_taxonomy( $post_type ),
				array(),
				$this->get_taxonomy_args( $post_type )
			);
		}
	}

	/**
	 * Attach each shadow taxonomy to the event CPTs declared via filter.
	 *
	 * For every shadow-source CPT, asks
	 * `gatherpress_shadow_taxonomy_object_types` for the list of event post
	 * types that should be taggable with the shadow's terms, then runs
	 * `register_taxonomy_for_object_type()` against each. The default filter
	 * value is an empty array — extensions opt in by returning the event
	 * CPTs they want their source connected to, so the wiring is explicit
	 * and discoverable in the hook docs.
	 *
	 * GatherPress's own venue subsystem hooks into this filter to wire
	 * `_gatherpress_venue` onto every `gatherpress-venue`-supporting event
	 * CPT, preserving the existing zero-config venue ↔ event relationship.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function attach_taxonomies_to_object_types(): void {
		foreach ( get_post_types_by_support( 'gatherpress-shadow-source' ) as $source_post_type ) {
			$taxonomy = $this->get_taxonomy( $source_post_type );

			/**
			 * Filters which event post types the shadow taxonomy should be
			 * attached to.
			 *
			 * Default is an empty array — extensions opt in by returning the
			 * event CPTs they want their shadow source linked to (saves
			 * callers from poking `register_taxonomy_for_object_type()`
			 * directly, and surfaces the wiring as a discoverable hook).
			 *
			 * Example — companion plugin registers `production` as a
			 * shadow source and wants events tagged with productions:
			 *
			 *     add_filter(
			 *         'gatherpress_shadow_taxonomy_object_types',
			 *         function ( $object_types, $source_post_type ) {
			 *             if ( 'production' === $source_post_type ) {
			 *                 $object_types[] = 'gatherpress_event';
			 *             }
			 *             return $object_types;
			 *         },
			 *         10,
			 *         2
			 *     );
			 *
			 * @since 0.34.0
			 *
			 * @param string[] $object_types     Event post types the shadow taxonomy attaches to.
			 * @param string   $source_post_type Shadow-source CPT slug whose taxonomy is being wired.
			 */
			$object_types = (array) apply_filters(
				'gatherpress_shadow_taxonomy_object_types',
				array(),
				$source_post_type
			);

			foreach ( array_unique( array_filter( $object_types ) ) as $object_type ) {
				register_taxonomy_for_object_type( $taxonomy, $object_type );
			}
		}
	}

	/**
	 * Return the taxonomy registration args for a shadow-source post type.
	 *
	 * Inherits its labels from the post type itself so the taxonomy reads
	 * naturally in admin columns and the Query Loop block's taxonomy
	 * controls. Filterable so consumers can override registration without
	 * forking the primitive.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that owns this shadow taxonomy.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_taxonomy_args( string $post_type ): array {
		$post_type_object = get_post_type_object( $post_type );
		$labels           = array(
			'name'          => $post_type_object instanceof WP_Post_Type
				? $post_type_object->labels->name
				: $post_type,
			'singular_name' => $post_type_object instanceof WP_Post_Type
				? $post_type_object->labels->singular_name
				: $post_type,
		);

		$args = array(
			'labels'             => $labels,
			'hierarchical'       => false,
			'public'             => true,
			'show_ui'            => false,
			'show_admin_column'  => true,
			'query_var'          => true,
			'publicly_queryable' => true,
			'rewrite'            => false,
			'show_in_rest'       => true,
		);

		/**
		 * Filters the taxonomy registration args for a shadow-source post type.
		 *
		 * Gives consumers a hook to tweak labels or other registration args
		 * for the shadow taxonomy without reimplementing the primitive.
		 *
		 * @since 0.34.0
		 *
		 * @param array<string, mixed> $args      The taxonomy registration args.
		 * @param string               $post_type The shadow-source post type slug.
		 */
		return (array) apply_filters( 'gatherpress_shadow_taxonomy_args', $args, $post_type );
	}

	/**
	 * Insert the shadow term when a shadow-source post is first published.
	 *
	 * Idempotent: if a term with the derived slug already exists, the call
	 * returns early. Skips autosaves and updates so the term is only created
	 * once, on initial publish.
	 *
	 * @since 0.34.0
	 *
	 * @param int     $post_id Post ID of the saved post.
	 * @param WP_Post $post    The saved post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function add_term( int $post_id, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { // @codeCoverageIgnore
			return; // @codeCoverageIgnore
		}

		// Skip non-shadow-source post types, updates (we only insert on the
		// initial publish), un-named or non-published posts in one guard so
		// the function reads top-down rather than as a return chain.
		if ( ! post_type_supports( $post->post_type, 'gatherpress-shadow-source' )
			|| $update
			|| empty( $post->post_name )
			|| 'publish' !== $post->post_status
		) {
			return;
		}

		$taxonomy  = $this->get_taxonomy( $post->post_type );
		$term_slug = $this->term_slug_from_post_name( $post->post_name );

		if ( term_exists( $term_slug, $taxonomy ) ) {
			return;
		}

		wp_insert_term(
			html_entity_decode( get_the_title( $post_id ) ),
			$taxonomy,
			array( 'slug' => $term_slug )
		);
	}

	/**
	 * Update the shadow term slug/title when its source post is renamed.
	 *
	 * Triggered on the global `post_updated` action so we can compare the
	 * pre/post post_name and post_title without re-reading from the DB. If
	 * the term doesn't exist yet (e.g. the post was created in draft and is
	 * now being renamed before first publish), one is created with the new
	 * slug.
	 *
	 * @since 0.34.0
	 *
	 * @param int     $post_id     Post ID of the updated post.
	 * @param WP_Post $post_after  Post object after the save operation.
	 * @param WP_Post $post_before Post object before the save operation.
	 *
	 * @return void
	 */
	public function maybe_update_term_slug( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		$post_type = (string) get_post_type( $post_id );

		// Combined guard: only run on shadow-source post types, only for
		// publish/trash transitions, and only when slug or title actually
		// changed (saves are otherwise no-ops here).
		if ( ! post_type_supports( $post_type, 'gatherpress-shadow-source' )
			|| ! in_array( $post_after->post_status, array( 'publish', 'trash' ), true )
			|| (
				$post_before->post_name === $post_after->post_name
				&& $post_before->post_title === $post_after->post_title
			)
		) {
			return;
		}

		// Derive both slugs from the hook-supplied post objects rather than
		// re-reading from the DB: the hook already gives us the trusted
		// pre/post state, and we avoid a race where a concurrent save would
		// leak into the slug calculation.
		$taxonomy      = $this->get_taxonomy( $post_type );
		$old_term_slug = $this->term_slug_from_post_name( $post_before->post_name );
		$new_term_slug = $this->term_slug_from_post_name( $post_after->post_name );
		$title         = html_entity_decode( get_the_title( $post_id ) );

		$term = term_exists( $old_term_slug, $taxonomy );

		if ( empty( $term ) ) {
			wp_insert_term(
				$title,
				$taxonomy,
				array( 'slug' => $new_term_slug )
			);
			return;
		}

		wp_update_term(
			intval( $term['term_id'] ),
			$taxonomy,
			array(
				'name' => $title,
				'slug' => $new_term_slug,
			)
		);
	}

	/**
	 * Delete the shadow term when its source post is deleted.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id Post ID of the post being deleted.
	 *
	 * @return void
	 */
	public function delete_term( int $post_id ): void {
		$post_type = (string) get_post_type( $post_id );

		if ( ! post_type_supports( $post_type, 'gatherpress-shadow-source' ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || empty( $post->post_name ) ) {
			return;
		}

		$taxonomy = $this->get_taxonomy( $post_type );
		$term     = get_term_by(
			'slug',
			$this->term_slug_from_post_name( $post->post_name ),
			$taxonomy
		);

		if ( $term instanceof WP_Term ) {
			wp_delete_term( $term->term_id, $taxonomy );
		}
	}

	/**
	 * Returns the taxonomy slug for a given shadow-source post type.
	 *
	 * The taxonomy slug is always derived by prepending an underscore to the
	 * post type slug — for example, `gatherpress_venue` → `_gatherpress_venue`.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The shadow-source post type slug.
	 *
	 * @return string The taxonomy slug for the given post type.
	 */
	public function get_taxonomy( string $post_type ): string {
		return '_' . $post_type;
	}

	/**
	 * Format a shadow taxonomy term slug from a post_name.
	 *
	 * Pure formatter — prepends an underscore to the given post_name. Used
	 * by callers that already have the name in hand (e.g. rename-diff
	 * callers comparing old vs. new post_name during a save-post transition).
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_name The post_name (e.g. `my-venue`).
	 *
	 * @return string The taxonomy term slug (e.g. `_my-venue`).
	 */
	public function term_slug_from_post_name( string $post_name ): string {
		return sprintf( '_%s', $post_name );
	}

	/**
	 * Returns true when `$slug` is a real shadow taxonomy term slug.
	 *
	 * Real shadow terms always carry a leading underscore — the auto-generated
	 * prefix added by {@see self::term_slug_from_post_name()}. Sentinels that
	 * may be added to the same taxonomy (such as the venue subsystem's
	 * `online-event` term) deliberately don't, so this predicate filters them
	 * out and keeps shadow-resolution logic from treating sentinels as real
	 * source posts.
	 *
	 * @since 0.34.0
	 *
	 * @param string $slug The term slug to test.
	 *
	 * @return bool
	 */
	public function is_shadow_term_slug( string $slug ): bool {
		return str_starts_with( $slug, '_' );
	}

	/**
	 * Retrieve the source post that corresponds to a shadow term slug.
	 *
	 * Strips the leading underscore from the taxonomy slug and looks up the
	 * post via `get_page_by_path()` against the given post type. Returns
	 * null when no matching post exists.
	 *
	 * @since 0.34.0
	 *
	 * @param string $slug      The shadow taxonomy term slug (e.g. `_my-venue`).
	 * @param string $post_type The shadow-source post type to search.
	 *
	 * @return WP_Post|null The matching post, or null.
	 */
	public function get_post_from_term_slug( string $slug, string $post_type ): ?WP_Post {
		return get_page_by_path( ltrim( $slug, '_' ), OBJECT, $post_type );
	}

	/**
	 * Retrieve the shadow-source post linked to an event post via shadow taxonomy.
	 *
	 * Generic version of the venue-specific lookup in
	 * {@see Venue\Setup::get_venue_post_from_event_post_id()} — given an event
	 * post ID and a shadow-source post type (e.g. `gatherpress_venue`,
	 * `gatherpress_tour`, `gatherpress_production`), walks the event's terms in
	 * the matching shadow taxonomy and returns the first term that resolves
	 * to an actual source post of that type.
	 *
	 * Skips sentinel terms (slugs without a leading underscore) such as the
	 * venue subsystem's `online-event` — those are markers, not pointers to
	 * source posts.
	 *
	 * @since 0.34.0
	 *
	 * @param int    $event_post_id    Event post ID to look up sources for.
	 * @param string $source_post_type Shadow-source post type to resolve against.
	 *
	 * @return WP_Post|null The linked source post, or null if none.
	 */
	public function get_source_post_from_event_post_id( int $event_post_id, string $source_post_type ): ?WP_Post {
		$taxonomy = $this->get_taxonomy( $source_post_type );
		$terms    = get_the_terms( $event_post_id, $taxonomy );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( ! $this->is_shadow_term_slug( $term->slug ) ) {
				continue;
			}

			$source_post = $this->get_post_from_term_slug( $term->slug, $source_post_type );

			if ( $source_post instanceof WP_Post ) {
				return $source_post;
			}
		}

		return null;
	}
}
