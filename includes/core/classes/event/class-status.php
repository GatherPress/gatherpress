<?php
/**
 * Vocabulary of the operational statuses an event can be in.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_Term;

/**
 * Class Status.
 *
 * One place that says what statuses exist and what each one means, so PHP,
 * the editor, the stylesheet and the calendar all read the same list rather
 * than keeping their own copies in step.
 *
 * Nothing here is fixed. The list below is a starting point a site can add
 * to, take from, or replace outright through `gatherpress_event_statuses`,
 * so no status is named in code that would have to change alongside it.
 *
 * The starting point is the whole of Schema.org's EventStatusType vocabulary,
 * with `moved` generalized: an event can move to a new venue as readily as it
 * can move online, and where it went is the location's business.
 *
 * @since 0.36.0
 */
final class Status {
	/**
	 * Schema.org value published for a status that names none of its own.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DEFAULT_SCHEMA = 'EventScheduled';

	/**
	 * RFC 5545 value published for a status that names none of its own.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DEFAULT_ICAL = 'CONFIRMED';

	/**
	 * Every status an event can be in, keyed by the slug that is stored.
	 *
	 * Each entry carries the words people read, the color it is shown in and
	 * the values the standards expect, so a status is one array entry rather
	 * than an edit in four places.
	 *
	 * @since 0.36.0
	 *
	 * A status can name the post type support it depends on, so one that only
	 * makes sense for events that can be held online is not offered on a post
	 * type that cannot.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Post type to offer statuses for, or empty for all of them.
	 *
	 * @return array<string, array<string, mixed>> The statuses, keyed by slug.
	 */
	public static function all( string $post_type = '' ): array {
		$statuses = array(
			'scheduled'   => array(
				'label'       => __( 'Scheduled', 'gatherpress' ),
				'description' => __( 'Event is planned and confirmed to take place.', 'gatherpress' ),
				'color'       => '#137333',
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
				'priority'    => 0,
			),
			'canceled'    => array(
				'label'       => __( 'Canceled', 'gatherpress' ),
				'description' => __(
					'Event will not take place. Calendar feeds will mark it as canceled.',
					'gatherpress'
				),
				'color'       => '#c5221f',
				'schema'      => 'EventCancelled',
				'ical'        => 'CANCELLED',
				'priority'    => 50,
			),
			'postponed'   => array(
				'label'       => __( 'Postponed', 'gatherpress' ),
				'description' => __( 'Event is delayed to a future unconfirmed date.', 'gatherpress' ),
				'color'       => '#b06000',
				'schema'      => 'EventPostponed',
				'ical'        => 'TENTATIVE',
				'priority'    => 40,
			),
			'rescheduled' => array(
				'label'       => __( 'Rescheduled', 'gatherpress' ),
				'description' => __( 'Event date and time have been changed.', 'gatherpress' ),
				'color'       => '#1a73e8',
				'schema'      => 'EventRescheduled',
				'ical'        => 'TENTATIVE',
				'priority'    => 30,
			),
			'moved'       => array(
				'label'       => __( 'Moved', 'gatherpress' ),
				'description' => __(
					'Event is taking place somewhere else, online or at another venue.',
					'gatherpress'
				),
				'color'       => '#7627bb',
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
				'priority'    => 20,
				// An event can only have moved if it can say where it is.
				'supports'    => array( 'gatherpress-venue', 'gatherpress-online-event' ),
			),
			'tentative'   => array(
				'label'       => __( 'Tentative', 'gatherpress' ),
				'description' => __(
					'Event is planned provisionally and awaiting confirmation.',
					'gatherpress'
				),
				'color'       => '#ea8600',
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
		 * holds the `label` and `description` people read, the `color` it is
		 * shown in, the `priority` used to resolve precedence among multiple
		 * statuses, and the `schema` (Schema.org EventStatusType) and `ical`
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
		 * @since 0.36.0
		 *
		 * @param array<string, array<string, mixed>> $statuses  The statuses, keyed by slug.
		 * @param string                              $post_type Post type they are offered for, or empty for all.
		 */
		return (array) apply_filters( 'gatherpress_event_statuses', $statuses, $post_type );
	}

	/**
	 * The slugs of every status, in the order they are offered.
	 *
	 * @since 0.36.0
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
	 * @since 0.36.0
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
	 * @since 0.36.0
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
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The label, or an empty string when none is known.
	 */
	public static function label( string $slug ): string {
		return (string) ( self::get( $slug )['label'] ?? '' );
	}

	/**
	 * The sentence explaining what a status means.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The description, or an empty string when none is known.
	 */
	public static function description( string $slug ): string {
		return (string) ( self::get( $slug )['description'] ?? '' );
	}

	/**
	 * The color a status is shown in.
	 *
	 * The badge derives its fill and border from this one value, so a status
	 * a site registers looks like its own rather than borrowing the default's
	 * color. Anything that is not a hex color or a CSS custom property is
	 * refused, since the value reaches a style attribute.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The color, or an empty string when there is none to use.
	 */
	public static function color( string $slug ): string {
		$color = (string) ( self::get( $slug )['color'] ?? '' );

		if (
			preg_match( '/^#[0-9a-f]{3,8}$/i', $color )
			|| preg_match( '/^var\(\s*--[\w-]+\s*(?:,[^;()]*)?\)$/', $color )
		) {
			return $color;
		}

		return '';
	}

	/**
	 * The Schema.org EventStatusType published for a status.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The Schema.org value.
	 */
	public static function schema( string $slug ): string {
		$schema = (string) ( self::get( $slug )['schema'] ?? '' );

		return '' === $schema ? self::DEFAULT_SCHEMA : $schema;
	}

	/**
	 * The RFC 5545 STATUS published for a status.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The iCalendar value.
	 */
	public static function ical( string $slug ): string {
		$ical = (string) ( self::get( $slug )['ical'] ?? '' );

		return '' === $ical ? self::DEFAULT_ICAL : $ical;
	}

	/**
	 * The priority a status carries when multiple statuses apply.
	 *
	 * A higher integer takes precedence over a lower one when a scalar
	 * value must be chosen for iCalendar STATUS or Schema.org eventStatus.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return int The priority, defaulting to 0 when unspecified.
	 */
	public static function priority( string $slug ): int {
		return (int) ( self::get( $slug )['priority'] ?? 0 );
	}

	/**
	 * Make sure a status has a term named the way people read it.
	 *
	 * `wp_set_object_terms()` creates a missing term named after the slug, so
	 * an event would show `canceled` where it should say `Canceled`. This
	 * puts the label on the term, and corrects one that already carries the
	 * wrong name, which is what a status renamed through the filter needs.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return void
	 */
	public static function ensure_term( string $slug ): void {
		$label = self::label( $slug );

		if ( '' === $label ) {
			return;
		}

		$term = get_term_by( 'slug', $slug, Event::TAXONOMY_STATUS );

		if ( ! $term instanceof WP_Term ) {
			wp_insert_term( $label, Event::TAXONOMY_STATUS, array( 'slug' => $slug ) );

			return;
		}

		if ( $label !== $term->name ) {
			wp_update_term( $term->term_id, Event::TAXONOMY_STATUS, array( 'name' => $label ) );
		}
	}

	/**
	 * One status's definition, falling back to the default one.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return array<string, mixed> The definition, or empty when none exist.
	 */
	private static function get( string $slug ): array {
		$statuses = self::all();

		return (array) ( $statuses[ $slug ] ?? $statuses[ self::default_slug() ] ?? array() );
	}
}
