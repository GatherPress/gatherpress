<?php
/**
 * A page for each date of a repeating event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Post;

/**
 * Class Date_Pages.
 *
 * Gives each date of a repeating event its own URL: the event's link plus
 * the date's key, such as `/event/weekly-meetup/20261218T190000/`, the form
 * events.wordpress.org already publishes. On that page the event shows that
 * date, and lists and the calendar link each date there.
 *
 * - A date-only URL, `/event/weekly-meetup/20261218/`, goes to that day's date.
 * - A key the series no longer has, within its dates, redirects: to the same
 *   day's date when only the time moved, or to the next date, with a notice
 *   saying the bookmarked date is no longer on the schedule.
 * - A malformed key, or one outside the series' dates, is a 404.
 *
 * Nothing is stored for this. Recording why a date was removed, and when,
 * comes with the exceptions table.
 *
 * @since TBD
 */
final class Date_Pages {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query var holding a date's key.
	 *
	 * @since TBD
	 * @var string
	 */
	const QUERY_VAR = 'gatherpress_occurrence';

	/**
	 * Query arg naming a date that is no longer on the schedule, after a redirect.
	 *
	 * @since TBD
	 * @var string
	 */
	const REMOVED_ARG = 'gatherpress_removed';

	/**
	 * A date key: `Ymd`, or `Ymd\THis`.
	 *
	 * @since TBD
	 * @var string
	 */
	const KEY_PATTERN = '\d{8}(?:T\d{6})?';

	/**
	 * Whether the notice has been added to this page's content.
	 *
	 * @since TBD
	 * @var bool
	 */
	protected bool $noticed = false;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register the routing, link and notice hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Priority 11: the event post types register on init at 10, and the rules read their slugs.
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 11 );
		add_action( 'template_redirect', array( $this, 'resolve' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'post_type_link', array( $this, 'link' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'add_notice' ) );
	}

	/**
	 * Route `/<slug>/<post>/<key>/` to the event, with the key, for every event post type.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			$object = get_post_type_object( $post_type );
			$slug   = is_array( $object->rewrite ?? null ) ? (string) ( $object->rewrite['slug'] ?? '' ) : '';

			if ( '' === $slug ) {
				continue;
			}

			add_rewrite_rule(
				sprintf( '^%s/([^/]+)/(%s)/?$', preg_quote( $slug, '#' ), self::KEY_PATTERN ),
				sprintf( 'index.php?post_type=%s&name=$matches[1]&%s=$matches[2]', $post_type, self::QUERY_VAR ),
				'top'
			);
		}
	}

	/**
	 * Allow the key as a query var.
	 *
	 * @since TBD
	 *
	 * @param string[] $vars The public query vars.
	 *
	 * @return string[] The vars, with the key's.
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * A date's URL: the event's link with the key.
	 *
	 * @since TBD
	 *
	 * @param string $permalink The event's link.
	 * @param string $key       The date's key.
	 *
	 * @return string The date's URL, as a path segment with pretty permalinks or a query arg without.
	 */
	public static function url( string $permalink, string $key ): string {
		if ( str_contains( $permalink, '?' ) ) {
			return add_query_arg( self::QUERY_VAR, $key, $permalink );
		}

		return user_trailingslashit( trailingslashit( $permalink ) . $key );
	}

	/**
	 * Link a repeating event's row, or its date page, to the date it shows.
	 *
	 * Hooked to `post_type_link`. Lists and the calendar call
	 * `get_permalink()` on each row, so each date links to its own page. An
	 * event without rules keeps its plain link.
	 *
	 * @since TBD
	 *
	 * @param string  $link The event's link.
	 * @param WP_Post $post The post.
	 *
	 * @return string The link, with the current date's key when there is one.
	 */
	public function link( string $link, WP_Post $post ): string {
		$occurrence = Current_Occurrence::get_instance()->get( $post->ID );
		$repeats    = $occurrence && get_post_meta( $post->ID, Rules::META_KEY, false );

		return $repeats ? self::url( $link, $occurrence['recurrence_id'] ) : $link;
	}

	/**
	 * Show the date a URL names, or send the visitor where that date went.
	 *
	 * Hooked to `template_redirect`.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function resolve(): void {
		$key  = (string) get_query_var( self::QUERY_VAR );
		$post = '' !== $key && is_singular() ? get_queried_object() : null;

		if ( ! $post instanceof WP_Post || ! post_type_supports( $post->post_type, Event::SUPPORT ) ) {
			return;
		}

		$event      = new Event( $post->ID );
		$valid      = 1 === preg_match( '/^' . self::KEY_PATTERN . '$/', $key );
		$occurrence = $valid ? $event->occurrences()->find( $key ) : null;

		if ( $occurrence ) {
			Current_Occurrence::get_instance()->set( $post->ID, self::fields( $occurrence ) );

			return;
		}

		$this->send_elsewhere( $event, $key );
	}

	/**
	 * Redirect a key the series doesn't have, or answer with a 404.
	 *
	 * @since TBD
	 *
	 * @param Event  $event The event.
	 * @param string $key   The key from the URL.
	 *
	 * @return void
	 */
	protected function send_elsewhere( Event $event, string $key ): void {
		$day       = $this->day_of( $key );
		$permalink = (string) get_permalink( $event->post->ID ?? 0 );
		$same_day  = $day ? $this->first_on( $event, $day ) : null;

		// The same day still has a date: only the time moved, or the URL named just the day.
		if ( $same_day ) {
			$this->redirect( self::url( $permalink, $same_day->recurrence_id ), 301 );

			return;
		}

		if ( ! $day || ! $this->within_dates( $event, $day ) ) {
			$this->not_found();

			return;
		}

		$next   = $this->next_after( $event, $day );
		$target = $next ? self::url( $permalink, $next->recurrence_id ) : $permalink;

		$this->redirect( add_query_arg( self::REMOVED_ARG, $key, $target ), 302 );
	}

	/**
	 * Say, above the content, that a bookmarked date is no longer on the schedule.
	 *
	 * Hooked to `the_content`. Only on the event a redirect landed on, once.
	 *
	 * @since TBD
	 *
	 * @param string $content The content.
	 *
	 * @return string The content, with the notice first when one is due.
	 */
	public function add_notice( string $content ): string {
		$removed = Utility::get_http_input( INPUT_GET, self::REMOVED_ARG );
		$day     = $this->day_of( $removed );
		$post_id = get_queried_object_id();

		if ( $this->noticed || ! $day || ! is_singular() || get_the_ID() !== $post_id ) {
			return $content;
		}

		$this->noticed = true;
		$format        = (string) get_option( 'date_format' );
		$date          = wp_date( $format, $day->getTimestamp(), new DateTimeZone( 'UTC' ) );
		$message       = Current_Occurrence::get_instance()->get( $post_id )
			/* translators: %s: A date. */
			? __( '%s is no longer on the schedule. This is the next date.', 'gatherpress' )
			/* translators: %s: A date. */
			: __( '%s is no longer on the schedule.', 'gatherpress' );

		return sprintf(
			'<p class="gatherpress-date-notice" role="status">%s</p>',
			esc_html( sprintf( $message, $date ) )
		) . $content;
	}

	/**
	 * An occurrence's date, in the shape `Current_Occurrence` holds.
	 *
	 * @since TBD
	 *
	 * @param Occurrence $occurrence The occurrence.
	 *
	 * @return array<string, string> The date, keyed by `Current_Occurrence::FIELDS`.
	 */
	public static function fields( Occurrence $occurrence ): array {
		$utc = new DateTimeZone( 'UTC' );

		return array(
			'recurrence_id'      => $occurrence->recurrence_id,
			'datetime_start'     => $occurrence->start->format( Event::DATETIME_FORMAT ),
			'datetime_start_gmt' => $occurrence->start->setTimezone( $utc )->format( Event::DATETIME_FORMAT ),
			'datetime_end'       => $occurrence->end->format( Event::DATETIME_FORMAT ),
			'datetime_end_gmt'   => $occurrence->end->setTimezone( $utc )->format( Event::DATETIME_FORMAT ),
			'timezone'           => $occurrence->start->getTimezone()->getName(),
		);
	}

	/**
	 * The local day a key names.
	 *
	 * @since TBD
	 *
	 * @param string $key A key, `Ymd` or `Ymd\THis`.
	 *
	 * @return DateTimeImmutable|null The day at midnight UTC, or null for a malformed key.
	 */
	protected function day_of( string $key ): ?DateTimeImmutable {
		if ( 1 !== preg_match( '/^(\d{8})(?:T(\d{6}))?$/', $key, $matches ) ) {
			return null;
		}

		$day = DateTimeImmutable::createFromFormat( '!Ymd', $matches[1], new DateTimeZone( 'UTC' ) );

		return $day && $day->format( 'Ymd' ) === $matches[1] ? $day : null;
	}

	/**
	 * The event's first date on a local day.
	 *
	 * @since TBD
	 *
	 * @param Event             $event The event.
	 * @param DateTimeImmutable $day   The day, at midnight UTC.
	 *
	 * @return Occurrence|null The date, or null if the day has none.
	 */
	protected function first_on( Event $event, DateTimeImmutable $day ): ?Occurrence {
		// A day's local dates fall within a day either side of it in UTC.
		$candidates = $event->occurrences()->in_range( $day->modify( '-1 day' ), $day->modify( '+2 days' ) );

		foreach ( $candidates as $occurrence ) {
			if ( $occurrence->start->format( 'Ymd' ) === $day->format( 'Ymd' ) ) {
				return $occurrence;
			}
		}

		return null;
	}

	/**
	 * The event's first date after a local day.
	 *
	 * @since TBD
	 *
	 * @param Event             $event The event.
	 * @param DateTimeImmutable $day   The day, at midnight UTC.
	 *
	 * @return Occurrence|null The date, or null if the series has none later.
	 */
	protected function next_after( Event $event, DateTimeImmutable $day ): ?Occurrence {
		$next = null;

		foreach ( $event->rules()->all() as $rule ) {
			$late = $this->first_of( $rule, $rule->next_date( $day->modify( '+1 day' ) ), $event->post->ID ?? 0 );

			if ( $late && ( ! $next || $late->start < $next->start ) ) {
				$next = $late;
			}
		}

		return $next;
	}

	/**
	 * A rule's first occurrence on a date.
	 *
	 * @since TBD
	 *
	 * @param Rule                   $rule    The rule.
	 * @param DateTimeImmutable|null $date    The date, or null when the rule has no more.
	 * @param int                    $post_id The event post ID.
	 *
	 * @return Occurrence|null The occurrence, or null without a date.
	 */
	protected function first_of( Rule $rule, ?DateTimeImmutable $date, int $post_id ): ?Occurrence {
		if ( ! $date ) {
			return null;
		}

		$occurrences = $rule->occurrences( $date, $date->modify( '+2 days' ), $post_id, Status::STATUS_SCHEDULED, 1 );

		return $occurrences[0] ?? null;
	}

	/**
	 * Whether a local day falls within a repeating event's dates, from its first to its last.
	 *
	 * @since TBD
	 *
	 * @param Event             $event The event.
	 * @param DateTimeImmutable $day   The day, at midnight UTC.
	 *
	 * @return bool True between the first rule's start and the last rule's end, or with no end.
	 */
	protected function within_dates( Event $event, DateTimeImmutable $day ): bool {
		foreach ( $event->rules()->all() as $rule ) {
			if ( $rule->starts_on <= $day && ( null === $rule->ends_on || $day <= $rule->ends_on ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Redirect and stop.
	 *
	 * @since TBD
	 *
	 * @param string $url    The URL.
	 * @param int    $status The HTTP status.
	 *
	 * @return void
	 */
	protected function redirect( string $url, int $status ): void {
		wp_safe_redirect( $url, $status );
		Utility::safe_exit();
	}

	/**
	 * Answer with the 404 page.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function not_found(): void {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
