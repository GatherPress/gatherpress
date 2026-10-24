<?php
/**
 * Renders the Calendar Week block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar\Date_Calculator;
use GatherPress\Core\Blocks\Calendar\Html_Renderer;
use WP_Block;

/**
 * Class Calendar_Week.
 *
 * Renders one table row of the calendar: an optional time gutter, an optional hidden week
 * number, and one gatherpress/calendar-day block per day.
 *
 * @since TBD
 *
 * @phpstan-import-type TemplateBlock from Html_Renderer
 */
final class Calendar_Week {

	/**
	 * Constant representing the Block Name.
	 *
	 * @since TBD
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/calendar-week';

	/**
	 * Calculates the maximum ribbon slot index across all days in a week.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $week_days Array of week day entries.
	 *
	 * @return int Maximum slot index found or -1 if none.
	 */
	private static function calculate_max_ribbon_slot( array $week_days ): int {
		$max_slot = -1;

		foreach ( $week_days as $day ) {
			$posts = is_array( $day ) && is_array( $day['posts'] ?? null ) ? $day['posts'] : array();

			foreach ( $posts as $post_entry ) {
				if ( is_array( $post_entry ) && empty( $post_entry['is_timed'] ) ) {
					$slot     = is_numeric( $post_entry['slot'] ?? null ) ? (int) $post_entry['slot'] : 0;
					$max_slot = max( $max_slot, $slot );
				}
			}
		}

		return $max_slot;
	}

	/**
	 * Renders the time gutter header cell when time grid is active.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $grid_config      Time grid configuration.
	 * @param int                  $num_ribbon_slots Number of ribbon slots.
	 *
	 * @return string Rendered time gutter HTML.
	 */
	private static function render_time_gutter( array $grid_config, int $num_ribbon_slots ): string {
		$slot_labels = '';
		$slots       = is_array( $grid_config['slots'] ?? null ) ? $grid_config['slots'] : array();

		foreach ( $slots as $slot_label ) {
			$slot_labels .= sprintf(
				'<div class="gatherpress-calendar__time-slot-label">%s</div>',
				esc_html( is_scalar( $slot_label ) ? (string) $slot_label : '' )
			);
		}

		// 28px accounts for the day-number row, plus any ribbon slots above the time grid.
		return sprintf(
			'<th scope="row" class="gatherpress-calendar__time-gutter" aria-hidden="true" '
				. 'style="padding-top: calc(28px + %d * 24px);">%s</th>',
			$num_ribbon_slots,
			$slot_labels
		);
	}

	/**
	 * Render the calendar week row.
	 *
	 * Called from the block's render.php.
	 *
	 * @since TBD
	 *
	 * @param WP_Block $block Block instance.
	 *
	 * @return string Rendered HTML <tr> row.
	 */
	public static function render( WP_Block $block ): string {
		$week_days    = is_array( $block->context['gatherpress/weekDays'] ?? null )
			? $block->context['gatherpress/weekDays']
			: array();
		$day_template = Html_Renderer::get_inner_template_block( $block, Calendar_Day::BLOCK_NAME );

		/**
		 * The time grid configuration from Date_Calculator::get_time_grid_config().
		 *
		 * @var array<string, mixed> $grid_config
		 */
		$grid_config  = is_array( $block->context['gatherpress/timeGridConfig'] ?? null )
			? $block->context['gatherpress/timeGridConfig']
			: array();
		$is_time_grid = ! empty( $grid_config['enabled'] );

		$num_ribbon_slots                             = self::calculate_max_ribbon_slot( $week_days ) + 1;
		$block->context['gatherpress/numRibbonSlots'] = $num_ribbon_slots;

		$today       = Date_Calculator::get_today();
		$days_html   = self::render_days( $week_days, $block, $day_template, $today, $num_ribbon_slots );
		$week_number = is_numeric( $block->context['gatherpress/weekNumber'] ?? null )
			? (int) $block->context['gatherpress/weekNumber']
			: 0;

		if ( $week_number > 0 ) {
			$days_html = sprintf(
				'<th scope="row" class="gatherpress--screen-reader-text">%s</th>',
				esc_html(
					sprintf(
						/* translators: %d: ISO 8601 week number. */
						__( 'Week %d', 'gatherpress' ),
						$week_number
					)
				)
			) . $days_html;
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class'       => 'gatherpress-calendar__week' . ( $is_time_grid ? ' has-time-grid' : '' ),
				'style'       => self::get_ribbon_rows_style( $num_ribbon_slots ),
				'data-wp-key' => self::get_row_key( $block->context ),
			)
		);

		return sprintf(
			'<tr %1$s>%2$s%3$s</tr>',
			$wrapper_attributes,
			$is_time_grid ? self::render_time_gutter( $grid_config, $num_ribbon_slots ) : '',
			$days_html
		);
	}

	/**
	 * Get the style of a week row with its number of ribbon rows.
	 *
	 * The time grid puts the ribbon rows above the quarter rows. Its CSS takes the ribbon rows
	 * from --gatherpress-ribbon-rows. For 0 the value is `initial`, so the CSS uses its empty
	 * fallback: repeat() with a count of 0 is not valid CSS and would drop the whole row
	 * template. `initial` also stops the value of an outer week from passing to a calendar
	 * inside one of its entries. The editor does the same (getRibbonRowsStyle() in
	 * src/blocks/calendar/utils/ribbon-rows.js).
	 *
	 * @since TBD
	 *
	 * @param int $num_ribbon_slots Number of ribbon rows in the week.
	 *
	 * @return string Inline style declarations.
	 */
	private static function get_ribbon_rows_style( int $num_ribbon_slots ): string {
		return sprintf(
			'--gatherpress-num-ribbon-slots: %d; --gatherpress-ribbon-rows: %s;',
			$num_ribbon_slots,
			$num_ribbon_slots > 0 ? sprintf( 'repeat(%d, 24px)', $num_ribbon_slots ) : 'initial'
		);
	}

	/**
	 * Count consecutive active (non-empty) columns remaining from the current column.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $week_days  Array of week day entries.
	 * @param int          $col_index  Current column index.
	 * @param int          $total_cols Total number of columns.
	 *
	 * @return int Number of active columns remaining.
	 */
	private static function count_remaining_active_columns( array $week_days, int $col_index, int $total_cols ): int {
		$cols_left = 0;

		for ( $c = $col_index; $c < $total_cols; $c++ ) {
			$day_entry = $week_days[ $c ] ?? null;

			if ( is_array( $day_entry ) && empty( $day_entry['isEmpty'] ) ) {
				++$cols_left;
			}
		}

		return $cols_left;
	}

	/**
	 * Annotate a single post entry with week-boundary and row-span descriptors.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $post_entry   Post entry descriptor.
	 * @param bool         $is_first_col Whether current column is first active column.
	 * @param bool         $is_last_col  Whether current column is last active column.
	 * @param int          $cols_left    Active columns remaining in row.
	 *
	 * @return array<mixed> Annotated post entry.
	 */
	private static function annotate_post_entry(
		array $post_entry,
		bool $is_first_col,
		bool $is_last_col,
		int $cols_left
	): array {
		$day_idx    = is_numeric( $post_entry['day_index'] ?? null ) ? (int) $post_entry['day_index'] : 1;
		$total_days = is_numeric( $post_entry['total_days'] ?? null ) ? (int) $post_entry['total_days'] : 1;

		if ( $total_days <= 1 ) {
			$post_entry['row_span'] = 1;

			return $post_entry;
		}

		$post_entry['is_continued'] = $is_first_col && $day_idx > 1;
		$post_entry['is_continues'] = $is_last_col && $day_idx < $total_days;
		$post_entry['row_span']     = max( 1, min( $total_days - $day_idx + 1, $cols_left ) );

		return $post_entry;
	}

	/**
	 * Annotate all day posts with week boundary wrapping and span metadata.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $day_posts  Posts on the day.
	 * @param int          $col_index  Column index.
	 * @param array<mixed> $week_days  All week day entries.
	 * @param int          $total_cols Total columns count.
	 *
	 * @return array<mixed> Annotated day posts.
	 */
	private static function annotate_day_posts(
		array $day_posts,
		int $col_index,
		array $week_days,
		int $total_cols
	): array {
		$prev_day     = $week_days[ $col_index - 1 ] ?? null;
		$next_day     = $week_days[ $col_index + 1 ] ?? null;
		$is_first_col = 0 === $col_index || ( is_array( $prev_day ) && ! empty( $prev_day['isEmpty'] ) );
		$is_last_col  = ( $total_cols - 1 ) === $col_index
			|| ( is_array( $next_day ) && ! empty( $next_day['isEmpty'] ) );
		$cols_left    = self::count_remaining_active_columns( $week_days, $col_index, $total_cols );

		foreach ( $day_posts as $index => $post_entry ) {
			if ( is_array( $post_entry ) ) {
				$day_posts[ $index ] = self::annotate_post_entry(
					$post_entry,
					$is_first_col,
					$is_last_col,
					$cols_left
				);
			}
		}

		return $day_posts;
	}

	/**
	 * Build context array for a single day block.
	 *
	 * @since TBD
	 *
	 * @param array<mixed>         $day              Day entry data.
	 * @param array<mixed>         $day_posts        Annotated day posts.
	 * @param array<string, mixed> $parent_context   Parent block context.
	 * @param string               $today            Today's Y-m-d date string.
	 * @param int                  $num_ribbon_slots Number of ribbon slots.
	 *
	 * @return array<string, mixed> Day block context.
	 */
	private static function build_day_context(
		array $day,
		array $day_posts,
		array $parent_context,
		string $today,
		int $num_ribbon_slots
	): array {
		$day_date = is_string( $day['date'] ?? null ) ? $day['date'] : '';

		return array_merge(
			$parent_context,
			array(
				'gatherpress/dayDate'        => $day_date,
				'gatherpress/dayNumber'      => $day['day'] ?? 0,
				'gatherpress/dayPosts'       => $day_posts,
				'gatherpress/isEmpty'        => ! empty( $day['isEmpty'] ),
				'gatherpress/isToday'        => '' !== $day_date && $day_date === $today,
				'gatherpress/weekday'        => $day['weekday'] ?? '',
				'gatherpress/isWeekend'      => ! empty( $day['isWeekend'] ),
				'gatherpress/numRibbonSlots' => $num_ribbon_slots,
			)
		);
	}

	/**
	 * Render all day blocks for a given week.
	 *
	 * @since TBD
	 *
	 * @param array<mixed>         $week_days        Array of week day entries.
	 * @param WP_Block             $block            Parent week block instance.
	 * @param array<string, mixed> $day_template     Parsed day template block.
	 * @param string               $today            Today's date in Y-m-d format.
	 * @param int                  $num_ribbon_slots Number of ribbon slots.
	 * @phpstan-param TemplateBlock $day_template
	 *
	 * @return string Rendered HTML of all day cells.
	 */
	private static function render_days(
		array $week_days,
		WP_Block $block,
		array $day_template,
		string $today,
		int $num_ribbon_slots
	): string {
		$output     = '';
		$total_cols = count( $week_days );

		foreach ( $week_days as $col_index => $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}

			$day_posts   = is_array( $day['posts'] ?? null ) ? $day['posts'] : array();
			$annotated   = self::annotate_day_posts( $day_posts, (int) $col_index, $week_days, $total_cols );
			$day_context = self::build_day_context( $day, $annotated, $block->context, $today, $num_ribbon_slots );
			$output     .= ( new WP_Block( $day_template, $day_context ) )->render();
		}

		return $output;
	}

	/**
	 * Build unique key attribute for the week row.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $context Block context.
	 *
	 * @return string Row key.
	 */
	private static function get_row_key( array $context ): string {
		$year       = is_numeric( $context['gatherpress/year'] ?? null ) ? (int) $context['gatherpress/year'] : 0;
		$month      = is_numeric( $context['gatherpress/month'] ?? null ) ? (int) $context['gatherpress/month'] : 0;
		$week_index = is_numeric( $context['gatherpress/weekIndex'] ?? null )
			? (int) $context['gatherpress/weekIndex']
			: 0;

		return sprintf( 'week-%d-%d-%d', $year, $month, $week_index );
	}
}
