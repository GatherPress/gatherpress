<?php
/**
 * Renders the Calendar block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar\Html_Renderer;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Post_Organizer;
use GatherPress\Core\Calendar\Query_Builder;
use GatherPress\Core\Calendar\Structure_Builder;
use GatherPress\Core\Calendar\Time_Grid;
use WP_Block;

/**
 * Class Calendar.
 *
 * Renders the gatherpress/calendar block: the date range of its Query block, the posts in that
 * range, and one table per month, week or day view unit.
 *
 * @since TBD
 */
final class Calendar {

	/**
	 * Constant representing the Block Name.
	 *
	 * @since TBD
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/calendar';

	/**
	 * Render the calendar block.
	 *
	 * Called from the block's render.php.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param WP_Block             $block      Block instance.
	 *
	 * @return string Rendered HTML, or an empty string outside a Query Loop.
	 */
	public static function render( array $attributes, WP_Block $block ): string {
		$query = $block->context['query'] ?? null;

		if ( ! is_array( $query ) || empty( $query ) ) {
			return '';
		}

		/**
		 * Block context keys are strings.
		 *
		 * @var array<string, mixed> $query
		 */
		$page       = Query_Builder::get_requested_page( $block->context['queryId'] ?? null );
		$date_range = Date_Range::from_query( $query, $attributes, $page, $block );

		// The time grid hours are attributes of the inner week block.
		$week_block = Html_Renderer::get_inner_template_block( $block, Calendar_Week::BLOCK_NAME );
		$week_attrs = $week_block['attrs'] ?? array();
		$time_grid  = Time_Grid::from_attributes(
			array_merge(
				$attributes,
				array(
					'dayStartHour' => $week_attrs['dayStartHour'] ?? 8,
					'dayEndHour'   => $week_attrs['dayEndHour'] ?? 20,
				)
			),
			$query
		);

		// Context for the week, day and entries blocks inside the calendar.
		$block->context['gatherpress/viewType']       = $date_range->view_type;
		$block->context['gatherpress/year']           = $date_range->year;
		$block->context['gatherpress/month']          = $date_range->month;
		$block->context['gatherpress/startDate']      = $date_range->start_date;
		$block->context['gatherpress/endDate']        = $date_range->end_date;
		$block->context['gatherpress/showWeekends']   = $date_range->week->show_weekends;
		$block->context['gatherpress/timeGridConfig'] = $time_grid;

		$post_ids      = ( new Query_Builder( $block, $date_range ) )->get_post_ids();
		$posts_by_date = ( new Post_Organizer( $date_range, $time_grid ) )->organize( $post_ids );
		$calendar_data = ( new Structure_Builder( $date_range ) )->build( $posts_by_date );
		$renderer      = new Html_Renderer( $block, $date_range, $time_grid );

		// The slot height is an attribute of the day block inside the week block.
		return $renderer->generate_calendar_html( $attributes, $calendar_data, self::get_slot_height( $week_block ) );
	}

	/**
	 * Get the time grid slot height from the day block of the week template.
	 *
	 * The editor uses the same attribute (calendar/edit.js) and the same range as its control.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $week_block Parsed week block of the calendar template.
	 *
	 * @return int Height of one hour slot in pixels, 48 by default.
	 */
	private static function get_slot_height( array $week_block ): int {
		$inner_blocks = is_array( $week_block['innerBlocks'] ?? null ) ? $week_block['innerBlocks'] : array();

		foreach ( $inner_blocks as $inner_block ) {
			if ( ! is_array( $inner_block ) || Calendar_Day::BLOCK_NAME !== ( $inner_block['blockName'] ?? '' ) ) {
				continue;
			}

			$slot_height = $inner_block['attrs']['slotHeight'] ?? null;

			return is_numeric( $slot_height ) ? max( 32, min( 120, (int) $slot_height ) ) : 48;
		}

		return 48;
	}
}
