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

use GatherPress\Core\Blocks\Calendar\Date_Calculator;
use GatherPress\Core\Blocks\Calendar\Html_Renderer;
use GatherPress\Core\Blocks\Calendar\Post_Organizer;
use GatherPress\Core\Blocks\Calendar\Query_Builder;
use GatherPress\Core\Blocks\Calendar\Structure_Builder;
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
		$page           = Query_Builder::get_requested_page( $block->context['queryId'] ?? null );
		$source_post_id = Date_Calculator::resolve_source_post_id( $attributes, $block );
		$date_range     = Date_Calculator::get_range_from_query( $query, $attributes, $page, $source_post_id );

		// The time grid hours are attributes of the inner week block.
		$week_block  = Html_Renderer::get_inner_template_block( $block, Calendar_Week::BLOCK_NAME );
		$week_attrs  = $week_block['attrs'] ?? array();
		$grid_config = Date_Calculator::get_time_grid_config(
			array_merge(
				$attributes,
				array(
					'dayStartHour' => $week_attrs['dayStartHour'] ?? 8,
					'dayEndHour'   => $week_attrs['dayEndHour'] ?? 20,
				)
			),
			$query
		);

		$show_weekends = Date_Calculator::show_weekends( $attributes );

		// Context for the week, day and entries blocks inside the calendar.
		$block->context['gatherpress/viewType']       = $date_range['view_type'];
		$block->context['gatherpress/year']           = $date_range['year'];
		$block->context['gatherpress/month']          = $date_range['month'];
		$block->context['gatherpress/startDate']      = $date_range['start_date'];
		$block->context['gatherpress/endDate']        = $date_range['end_date'];
		$block->context['gatherpress/showWeekends']   = $show_weekends;
		$block->context['gatherpress/timeGridConfig'] = $grid_config;

		$posts_by_date = Post_Organizer::organize_posts_by_date(
			Query_Builder::build_query_args( $block, $date_range ),
			$grid_config
		);
		$calendar_data = Structure_Builder::build_structure(
			$date_range,
			Date_Calculator::get_start_of_week(),
			$posts_by_date,
			$show_weekends
		);

		return ( new Html_Renderer( $block ) )->generate_calendar_html( $attributes, $calendar_data, $grid_config );
	}
}
