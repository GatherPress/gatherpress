<?php
/**
 * Renders the markup of the Event Date block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Event_Date;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Settings;
use GatherPress\Core\User;
use GatherPress\Core\Utility;
use WP_Block;

/**
 * Class Html_Renderer.
 *
 * Generates the HTML output of the Event Date block: the event datetime, an optional link
 * to the event, and the context the view module needs to show the viewer's local time.
 *
 * @since TBD
 */
final class Html_Renderer {

	/**
	 * The block attributes.
	 *
	 * @since TBD
	 * @var array<string, mixed>
	 */
	private array $attributes;

	/**
	 * The event post ID.
	 *
	 * @since TBD
	 * @var int
	 */
	private int $post_id;

	/**
	 * The event the block shows.
	 *
	 * @since TBD
	 * @var Event
	 */
	private Event $event;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param WP_Block             $block      The gatherpress/event-date block instance.
	 */
	public function __construct( array $attributes, WP_Block $block ) {
		$this->attributes = $attributes;
		$this->post_id    = Setup::get_instance()->get_post_id( $block->parsed_block );
		$this->event      = new Event( $this->post_id );
	}

	/**
	 * Render the block.
	 *
	 * @since TBD
	 *
	 * @return string The block markup.
	 */
	public function render(): string {
		$context      = $this->get_viewer_time_context();
		$event_status = new Status( $this->post_id );
		$status       = $event_status->get();
		$status_label = '';
		$classes      = array();

		if ( Status::default_slug( (string) get_post_type( $this->post_id ) ) !== $status ) {
			$classes[]    = sprintf( 'gatherpress-event-date--is-%s', sanitize_html_class( $status ) );
			$status_label = $event_status->get_label();
		}

		$wrapper_attributes = empty( $classes )
			? get_block_wrapper_attributes()
			: get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );

		if ( ! empty( $context ) ) {
			$wrapper_attributes .= ' data-wp-interactive="gatherpress" ' . wp_interactivity_data_wp_context( $context );
		}

		return Utility::render_template(
			sprintf( '%s/includes/templates/blocks/event-date.php', GATHERPRESS_CORE_PATH ),
			array(
				'wrapper_attributes' => $wrapper_attributes,
				'datetime'           => $this->get_datetime_markup(),
				'link'               => $this->get_link(),
				'has_viewer_time'    => ! empty( $context ),
				'status_label'       => $status_label,
			)
		);
	}

	/**
	 * Get the event URL when the block links to it.
	 *
	 * @since TBD
	 *
	 * @return string The URL, or an empty string when the block is not a link.
	 */
	private function get_link(): string {
		return empty( $this->attributes['isLink'] ) ? '' : (string) get_permalink( $this->post_id );
	}

	/**
	 * Get the event datetime, with a machine-readable time element for each date shown.
	 *
	 * @since TBD
	 *
	 * @return string The escaped datetime markup.
	 */
	private function get_datetime_markup(): string {
		$parts = $this->event->get_display_datetime_parts(
			(string) ( $this->attributes['displayType'] ?? '' ),
			(string) ( $this->attributes['startDateFormat'] ?? '' ),
			(string) ( $this->attributes['endDateFormat'] ?? '' ),
			(string) ( $this->attributes['separator'] ?? '' ),
			(string) ( $this->attributes['showTimezone'] ?? '' )
		);

		$markup = array_filter(
			array(
				$this->render_time( (string) $parts['start'], $this->event->get_datetime_start_iso() ),
				esc_html( (string) $parts['separator'] ),
				$this->render_time( (string) $parts['end'], $this->event->get_datetime_end_iso() ),
				esc_html( (string) $parts['timezone'] ),
			)
		);

		return $markup ? implode( ' ', $markup ) : esc_html( Event::DATETIME_PLACEHOLDER );
	}

	/**
	 * Render one date, as a time element when its ISO 8601 datetime is known.
	 *
	 * @since TBD
	 *
	 * @param string $human The date as shown.
	 * @param string $iso   The ISO 8601 datetime.
	 *
	 * @return string The escaped markup, or an empty string when the date is not shown.
	 */
	private function render_time( string $human, string $iso ): string {
		if ( '' === $human ) {
			return '';
		}

		if ( '' === $iso ) {
			return esc_html( $human );
		}

		return sprintf( '<time datetime="%s">%s</time>', esc_attr( $iso ), esc_html( $human ) );
	}

	/**
	 * Check whether the block appends the timezone, so whichever time it shows is labeled.
	 *
	 * Mirrors `isTimezoneAppended()` in src/blocks/event-date/helpers.js.
	 *
	 * @since TBD
	 *
	 * @return bool True when the timezone is shown.
	 */
	private function is_timezone_shown(): bool {
		$preference = $this->event->get_timezone_preference();

		if ( 'always' === $preference || 'never' === $preference ) {
			return 'always' === $preference;
		}

		$show_timezone = (string) ( $this->attributes['showTimezone'] ?? '' );

		return '' !== $show_timezone
			? 'yes' === $show_timezone
			: (bool) Settings::get_instance()->get( 'show_timezone' );
	}

	/**
	 * Check whether the block shows the viewer's local time.
	 *
	 * @since TBD
	 *
	 * @return bool True when the viewer time tooltip applies.
	 */
	private function shows_viewer_time(): bool {
		return ! empty( $this->attributes['showViewerTime'] )
			&& $this->is_timezone_shown()
			&& Settings::get_instance()->get( 'show_viewer_timezone' )
			&& ! $this->event->is_all_day()
			// A saved timezone already converts the main line, and the browser's guess must not contradict it.
			&& '' === User::get_instance()->get_saved_timezone();
	}

	/**
	 * Get the context the view module turns into the viewer's local time.
	 *
	 * Only the browser knows the viewer's timezone, so this carries the GMT datetimes the
	 * block displays and the timezone they are shown in. The view script is a module and
	 * cannot import `@wordpress/i18n`, so the sentences it fills in are translated here.
	 *
	 * @since TBD
	 *
	 * @return array<string, string> The context, or an empty array when the block shows no viewer time.
	 */
	private function get_viewer_time_context(): array {
		if ( ! $this->shows_viewer_time() ) {
			return array();
		}

		// An empty display type shows both, as in Event::get_display_datetime() and edit.js.
		$display_type = (string) ( $this->attributes['displayType'] ?? '' );
		$display_type = '' !== $display_type ? $display_type : 'both';
		$datetime     = $this->event->get_datetime();
		$start_gmt    = 'end' !== $display_type ? $datetime['datetime_start_gmt'] : '';
		$end_gmt      = 'start' !== $display_type ? $datetime['datetime_end_gmt'] : '';

		if ( '' === $start_gmt && '' === $end_gmt ) {
			return array();
		}

		return array(
			'startGmt'      => $start_gmt,
			'endGmt'        => $end_gmt,
			'eventTimezone' => $datetime['timezone'],
			'rangeFormat'   => $this->get_range_format(),
			/* translators: %s: event start in the viewer's timezone. */
			'singleFormat'  => __( '%s your time', 'gatherpress' ),
		);
	}

	/**
	 * Get the sentence for a start and end in the viewer's timezone, with the block's separator.
	 *
	 * @since TBD
	 *
	 * @return string The format, taking `%1$s` start and `%2$s` end.
	 */
	private function get_range_format(): string {
		$separator = (string) ( $this->attributes['separator'] ?? '' );
		$separator = '' !== $separator ? $separator : __( 'to', 'gatherpress' );

		/** This filter is documented in includes/core/classes/event/class-event.php */
		$separator = apply_filters( 'gatherpress_datetime_separator', $separator, $this->event );

		return sprintf(
			/* translators: 1: start in the viewer's timezone, 2: separator, 3: end in the viewer's timezone. */
			__( '%1$s %2$s %3$s your time', 'gatherpress' ),
			'%1$s',
			$separator,
			'%2$s'
		);
	}
}
