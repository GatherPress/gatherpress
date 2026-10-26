<?php
/**
 * Registers the block binding sources and editor settings of the Calendar blocks.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeZone;
use GatherPress\Core\Blocks\Calendar;
use GatherPress\Core\Blocks\Calendar_Day;
use GatherPress\Core\Blocks\Calendar_Entries;
use GatherPress\Core\Blocks\Calendar_Week;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Query_Builder;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Calendar\Week;
use GatherPress\Core\Traits\Singleton;
use WP_Block;

/**
 * Class Registration.
 *
 * The binding sources give a Paragraph the day number of its calendar day, and a Heading the
 * date range of its calendar. The editor settings give the editor preview the same week start,
 * weekend days, post limit and time grid rows as the front end.
 *
 * @since TBD
 */
final class Registration {

	/**
	 * The calendar blocks, which render their inner blocks themselves.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const SELF_RENDERING_BLOCKS = array(
		Calendar::BLOCK_NAME,
		Calendar_Week::BLOCK_NAME,
		Calendar_Day::BLOCK_NAME,
		Calendar_Entries::BLOCK_NAME,
	);

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Priority 11: this class is created on init at priority 10.
		add_action( 'init', array( $this, 'register_binding_sources' ), 11 );
		add_filter( 'block_editor_settings_all', array( $this, 'block_editor_settings_all' ) );
		// The blocks register on init at priority 11, after this class is created.
		add_filter( 'register_block_type_args', array( $this, 'skip_inner_blocks' ), 10, 2 );
	}

	/**
	 * Stop WordPress from rendering the inner blocks of the calendar blocks before their render.
	 *
	 * Each calendar block renders its template itself: once for each week, day or entry, with
	 * that unit's context. Without this, WP_Block::render() also renders the template once
	 * without that context, and the output is thrown away. Core's Post Template does the same.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $args       Block type arguments.
	 * @param string               $block_type Block type name.
	 *
	 * @return array<string, mixed> Block type arguments.
	 */
	public function skip_inner_blocks( array $args, string $block_type ): array {
		if ( in_array( $block_type, self::SELF_RENDERING_BLOCKS, true ) ) {
			$args['skip_inner_blocks'] = true;
		}

		return $args;
	}

	/**
	 * Register the block binding sources of the calendar.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_binding_sources(): void {
		register_block_bindings_source(
			'gatherpress/calendar-day',
			array(
				'label'              => _x( 'Calendar Day Number', 'Block Bindings Source', 'gatherpress' ),
				'get_value_callback' => array( $this, 'get_day_number_binding_value' ),
				'uses_context'       => array( 'gatherpress/dayNumber', 'gatherpress/dayDate', 'gatherpress/isEmpty' ),
			)
		);

		register_block_bindings_source(
			'gatherpress/calendar-heading',
			array(
				'label'              => _x( 'Calendar Heading', 'Block Bindings Source', 'gatherpress' ),
				'get_value_callback' => array( $this, 'get_heading_binding_value' ),
				'uses_context'       => array( 'query', 'queryId', 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Pass calendar settings into the block editor settings.
	 *
	 * The editor preview then uses the same start of week, weekend days, post limit and hours per
	 * time grid row as the front end. Other values under `gatherpress` stay.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $settings Default editor settings.
	 *
	 * @return array<string, mixed> Filtered editor settings.
	 */
	public function block_editor_settings_all( array $settings ): array {
		$gatherpress = is_array( $settings['gatherpress'] ?? null ) ? $settings['gatherpress'] : array();
		$week        = new Week();

		$gatherpress['weekendDays']      = $week->weekend_days;
		$gatherpress['postsPerPage']     = Query_Builder::get_posts_per_page();
		$gatherpress['startOfWeek']      = $week->start_of_week;
		$gatherpress['timeSlotDuration'] = Time_Grid::get_slot_duration();
		$settings['gatherpress']         = $gatherpress;

		return $settings;
	}

	/**
	 * Day number binding value callback.
	 *
	 * Supports optional 'format' argument from binding args (e.g. 'j', 'd', 'jS', 'j.', 'D j').
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $source_args    Source arguments.
	 * @param WP_Block             $block_instance Block instance.
	 * @param string               $attribute_name Attribute name.
	 *
	 * @return string|null The day number, an empty string for an empty cell, or null.
	 */
	public function get_day_number_binding_value(
		array $source_args,
		WP_Block $block_instance,
		string $attribute_name
	): ?string {
		if ( 'content' !== $attribute_name ) {
			return null;
		}

		if ( ! empty( $block_instance->context['gatherpress/isEmpty'] ) ) {
			return '';
		}

		$day_date = $block_instance->context['gatherpress/dayDate'] ?? '';
		$format   = is_string( $source_args['format'] ?? null ) ? $source_args['format'] : '';

		if ( is_string( $day_date ) && '' !== $day_date && '' !== $format ) {
			$timestamp = strtotime( $day_date . ' 12:00:00 UTC' );

			if ( false !== $timestamp ) {
				return (string) wp_date( $format, $timestamp, new DateTimeZone( 'UTC' ) );
			}
		}

		$day_number = $block_instance->context['gatherpress/dayNumber'] ?? null;

		return ( is_numeric( $day_number ) || is_string( $day_number ) ) ? (string) $day_number : null;
	}

	/**
	 * Calendar heading binding value callback. Supports month, week, and day views.
	 *
	 * Reads the same Query pagination that Calendar_Query uses to page the calendar,
	 * so a heading bound to this source (anywhere inside the same Query block) always
	 * shows the date range that the calendar shows.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $source_args    Source arguments.
	 * @param WP_Block             $block_instance Block instance.
	 * @param string               $attribute_name Attribute name.
	 *
	 * @return string|null Localized heading string or null.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) -- $source_args is required by the binding callback signature.
	 */
	public function get_heading_binding_value(
		array $source_args,
		WP_Block $block_instance,
		string $attribute_name
	): ?string {
		if ( 'content' !== $attribute_name ) {
			return null;
		}

		$query = $block_instance->context['query'] ?? null;

		if ( ! is_array( $query ) ) {
			return (string) wp_date(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'F Y', 'Calendar heading: single month', 'gatherpress' ),
				current_datetime()->getTimestamp()
			);
		}

		/**
		 * Block context keys are strings.
		 *
		 * @var array<string, mixed> $query
		 */
		$page = Query_Builder::get_requested_page( $block_instance->context['queryId'] ?? null );

		return Date_Range::from_query( $query, array(), $page )->heading;
	}
}
