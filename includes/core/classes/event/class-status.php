<?php
/**
 * Vocabulary of the operational statuses an event can be in.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_Post;
use WP_Term;

/**
 * Class Status.
 *
 * One place that says what statuses exist and what each one means, so PHP,
 * the editor and the calendar all read the same list rather than keeping
 * their own copies in step.
 *
 * Nothing here is fixed. The list below is a starting point a site can add
 * to, take from, or replace outright through `gatherpress_event_statuses`,
 * so no status is named in code that would have to change alongside it.
 *
 * The starting point is the whole of Schema.org's EventStatusType vocabulary.
 *
 * An instance reads and writes one event's status. The static methods are
 * the vocabulary itself, for callers that have no event to hand, such as
 * the REST schema, the editor settings and the taxonomy's default term.
 *
 * @since TBD
 */
final class Status {
	/**
	 * Schema.org value published for a status that names none of its own.
	 *
	 * @since TBD
	 * @var string
	 */
	const DEFAULT_SCHEMA = 'EventScheduled';

	/**
	 * RFC 5545 value published for a status that names none of its own.
	 *
	 * @since TBD
	 * @var string
	 */
	const DEFAULT_ICAL = 'CONFIRMED';

	/**
	 * The event post, or null when the post does not support events.
	 *
	 * @since TBD
	 *
	 * @var WP_Post|null
	 */
	protected ?WP_Post $post = null;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		if ( post_type_supports( (string) get_post_type( $post_id ), Event::SUPPORT ) ) {
			$this->post = get_post( $post_id );
		}
	}

	/**
	 * Every status an event can be in, keyed by the slug that is stored.
	 *
	 * Each entry carries the words people read and the values the standards
	 * expect, so a status is one array entry rather than an edit in several
	 * places.
	 *
	 * A status can name the post type support it depends on, so one that only
	 * makes sense for events that can be held online is not offered on a post
	 * type that cannot.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type to offer statuses for, or empty for all of them.
	 *
	 * @return array<string, array<string, mixed>> The statuses, keyed by slug.
	 */
	public static function all( string $post_type = '' ): array {
		$statuses = array(
			'scheduled'    => array(
				/* translators: Event operational status label. */
				'label'       => _x( 'Scheduled', 'event status', 'gatherpress' ),
				'description' => __( 'Event is planned and confirmed to take place.', 'gatherpress' ),
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
				'priority'    => 0,
			),
			'canceled'     => array(
				/* translators: Event operational status label. */
				'label'       => _x( 'Canceled', 'event status', 'gatherpress' ),
				'description' => __(
					'Event will not take place. Calendar feeds will mark it as canceled.',
					'gatherpress'
				),
				'schema'      => 'EventCancelled',
				'ical'        => 'CANCELLED',
				'priority'    => 50,
			),
			'postponed'    => array(
				/* translators: Event operational status label. */
				'label'       => _x( 'Postponed', 'event status', 'gatherpress' ),
				'description' => __( 'Event is delayed to a future unconfirmed date.', 'gatherpress' ),
				'schema'      => 'EventPostponed',
				'ical'        => 'TENTATIVE',
				'priority'    => 40,
			),
			'rescheduled'  => array(
				/* translators: Event operational status label. */
				'label'       => _x( 'Rescheduled', 'event status', 'gatherpress' ),
				'description' => __( 'Event date and time have been changed.', 'gatherpress' ),
				'schema'      => 'EventRescheduled',
				'ical'        => 'TENTATIVE',
				'priority'    => 30,
			),
			'moved-online' => array(
				/* translators: Event operational status label. */
				'label'       => _x( 'Moved online', 'event status', 'gatherpress' ),
				'description' => __(
					'Event venue has changed to an online meeting.',
					'gatherpress'
				),
				'schema'      => 'EventMovedOnline',
				'ical'        => 'CONFIRMED',
				'priority'    => 20,
				// An event can only have moved online if it supports online events.
				'supports'    => array( 'gatherpress-online-event' ),
			),
			'tentative'    => array(
				/* translators: Event operational status label. */
				'label'       => _x( 'Tentative', 'event status', 'gatherpress' ),
				'description' => __(
					'Event is planned provisionally and awaiting confirmation.',
					'gatherpress'
				),
				'schema'      => 'EventScheduled',
				'ical'        => 'TENTATIVE',
				'priority'    => 10,
			),
		);

		if ( '' !== $post_type ) {
			$statuses = array_filter(
				$statuses,
				static function ( $status ) use ( $post_type ): bool {
					$supports = (array) ( $status['supports'] ?? array() );

					if ( empty( $supports ) ) {
						return true;
					}

					foreach ( $supports as $support ) {
						if ( post_type_supports( $post_type, (string) $support ) ) {
							return true;
						}
					}

					return false;
				}
			);
		}

		/**
		 * Filters the operational statuses an event can be in.
		 *
		 * Each status is keyed by the slug stored against the event. An entry
		 * holds the `label` and `description` people read, the `priority` used
		 * to resolve precedence among multiple statuses, and the `schema` (Schema.org EventStatusType) and `ical`
		 * (RFC 5545 STATUS) values published for it. Anything omitted falls
		 * back to something that says the event is going ahead, so a status
		 * never tells a calendar client something untrue.
		 *
		 * Statuses can be added, removed or replaced, and this is the last
		 * word: the built-in `supports` conditions have already been applied
		 * against `$post_type` by the time this runs, so a status kept off a
		 * post type can be put back, and one that does not suit a post type
		 * can be taken away. `$post_type` is empty when the whole vocabulary
		 * is being asked for rather than one post type's share of it.
		 *
		 * The first status in the list is what an event with no status of its
		 * own reports, so order matters.
		 *
		 * @since TBD
		 *
		 * @param array<string, array<string, mixed>> $statuses  The statuses, keyed by slug.
		 * @param string                              $post_type Post type they are offered for, or empty for all.
		 */
		return apply_filters( 'gatherpress_event_statuses', $statuses, $post_type );
	}

	/**
	 * The slugs of every status, in the order they are offered.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type to offer statuses for, or empty for all.
	 *
	 * @return string[] The status slugs.
	 */
	public static function slugs( string $post_type = '' ): array {
		return array_keys( self::all( $post_type ) );
	}

	/**
	 * The status an event reports when it has none of its own.
	 *
	 * The first status offered, rather than a named one, so a site that
	 * replaces the vocabulary outright still has a state to fall back on.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type to answer for, or empty for all.
	 *
	 * @return string The default slug, or an empty string when none exist.
	 */
	public static function default_slug( string $post_type = '' ): string {
		$slugs = self::slugs( $post_type );

		return (string) ( $slugs[0] ?? '' );
	}

	/**
	 * Whether a slug names a status an event can be in.
	 *
	 * @since TBD
	 *
	 * @param string $slug      The slug to check.
	 * @param string $post_type Post type to check against, or empty for all.
	 *
	 * @return bool True when the status exists.
	 */
	public static function exists( string $slug, string $post_type = '' ): bool {
		return isset( self::all( $post_type )[ $slug ] );
	}

	/**
	 * The words shown for a status.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The label, or an empty string when none is known.
	 */
	public static function label( string $slug ): string {
		return (string) ( self::definition( $slug )['label'] ?? '' );
	}

	/**
	 * The sentence explaining what a status means.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The description, or an empty string when none is known.
	 */
	public static function description( string $slug ): string {
		return (string) ( self::definition( $slug )['description'] ?? '' );
	}

	/**
	 * The Schema.org EventStatusType published for a status.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The Schema.org value.
	 */
	public static function schema( string $slug ): string {
		$schema = (string) ( self::definition( $slug )['schema'] ?? '' );

		return '' === $schema ? self::DEFAULT_SCHEMA : $schema;
	}

	/**
	 * The RFC 5545 STATUS published for a status.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The iCalendar value.
	 */
	public static function ical( string $slug ): string {
		$ical = (string) ( self::definition( $slug )['ical'] ?? '' );

		return '' === $ical ? self::DEFAULT_ICAL : $ical;
	}

	/**
	 * The priority a status carries when multiple statuses apply.
	 *
	 * A higher integer takes precedence over a lower one when a scalar
	 * value must be chosen for iCalendar STATUS or Schema.org eventStatus.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return int The priority, defaulting to 0 when unspecified.
	 */
	public static function priority( string $slug ): int {
		return (int) ( self::definition( $slug )['priority'] ?? 0 );
	}

	/**
	 * Make sure a status has a term created.
	 *
	 * Creates a missing term for the status if it does not yet exist. Existing
	 * terms are left alone in the database so saving events does not rewrite
	 * term names across different user locales. Localized display names are
	 * handled dynamically via filters at retrieval and render time.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return void
	 */
	public static function ensure_term( string $slug ): void {
		if ( ! self::exists( $slug ) ) {
			return;
		}

		$term = get_term_by( 'slug', $slug, Event::TAXONOMY_STATUS );

		if ( ! $term instanceof WP_Term ) {
			wp_insert_term( self::label( $slug ), Event::TAXONOMY_STATUS, array( 'slug' => $slug ) );
		}
	}

	/**
	 * Every status slug assigned to the event, highest priority first.
	 *
	 * Defaults to the default status when the event has none of its own.
	 *
	 * @since TBD
	 *
	 * @return string[] The status slugs assigned to the event.
	 */
	public function get_all(): array {
		if ( ! $this->post ) {
			return array( self::default_slug() );
		}

		$post_type = (string) $this->post->post_type;
		$terms     = get_the_terms( $this->post->ID, Event::TAXONOMY_STATUS );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return array( self::default_slug( $post_type ) );
		}

		$valid_statuses = array();

		foreach ( $terms as $term ) {
			$slug = (string) $term->slug;

			if ( self::exists( $slug, $post_type ) ) {
				$valid_statuses[] = $slug;
			}
		}

		if ( empty( $valid_statuses ) ) {
			return array( self::default_slug( $post_type ) );
		}

		usort(
			$valid_statuses,
			static function ( string $a, string $b ): int {
				return self::priority( $b ) <=> self::priority( $a );
			}
		);

		return array_values( array_unique( $valid_statuses ) );
	}

	/**
	 * The event's status.
	 *
	 * When an event holds several statuses, the one with the highest priority
	 * wins for single-value consumers like iCalendar STATUS.
	 *
	 * @since TBD
	 *
	 * @return string The status slug.
	 */
	public function get(): string {
		return $this->get_all()[0] ?? self::default_slug();
	}

	/**
	 * Store the event's status.
	 *
	 * @since TBD
	 *
	 * @param string $status One of the slugs Status::slugs() reports.
	 * @param bool   $append Optional. When true, appends to existing terms
	 *                       instead of replacing them. Default false.
	 *
	 * @return bool True when the status was stored.
	 */
	public function set( string $status, bool $append = false ): bool {
		if ( ! $this->post || ! self::exists( $status, (string) $this->post->post_type ) ) {
			return false;
		}

		self::ensure_term( $status );

		// A plain string replaces every term in the taxonomy by default, which is
		// what enforces the statuses being mutually exclusive.
		$result = wp_set_object_terms( $this->post->ID, $status, Event::TAXONOMY_STATUS, $append );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Terms are written without touching the post row, but the calendar
		// reads `post_modified_gmt` for a VEVENT's `SEQUENCE`, and a client
		// ignores an update whose sequence has not advanced. Touching the post
		// is what tells a subscriber that a cancellation actually happened.
		return ! is_wp_error( wp_update_post( array( 'ID' => $this->post->ID ), true ) );
	}

	/**
	 * The words shown for the event's status.
	 *
	 * @since TBD
	 *
	 * @return string The status label.
	 */
	public function get_label(): string {
		return self::label( $this->get() );
	}

	/**
	 * The Schema.org EventStatusType for the event's status.
	 *
	 * @since TBD
	 *
	 * @return string The Schema.org value, such as 'EventScheduled'.
	 */
	public function get_schema(): string {
		return self::schema( $this->get() );
	}

	/**
	 * The RFC 5545 STATUS for the event's status.
	 *
	 * @since TBD
	 *
	 * @return string 'CONFIRMED', 'CANCELLED' or 'TENTATIVE'.
	 */
	public function get_ical(): string {
		return self::ical( $this->get() );
	}

	/**
	 * One status's definition, falling back to the default one.
	 *
	 * @since TBD
	 *
	 * @param string $slug The status slug.
	 *
	 * @return array<string, mixed> The definition, or empty when none exist.
	 */
	private static function definition( string $slug ): array {
		$statuses = self::all();

		return (array) ( $statuses[ $slug ] ?? $statuses[ self::default_slug() ] ?? array() );
	}
}
