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

use GatherPress\Core\Blocks\Calendar\Base;
use GatherPress\Core\Blocks\Calendar\Html_Renderer;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Time_Grid;
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
final class Calendar_Week extends Base {

	/**
	 * Constant representing the Block Name.
	 *
	 * @since TBD
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/calendar-week';

	/**
	 * Day entries of the week, from the block context.
	 *
	 * @since TBD
	 * @var array<mixed>
	 */
	private readonly array $week_days;

	/**
	 * Time grid of the calendar.
	 *
	 * @since TBD
	 * @var Time_Grid
	 */
	private readonly Time_Grid $time_grid;

	/**
	 * Number of ribbon rows the week's multi-day and all-day events need.
	 *
	 * @since TBD
	 * @var int
	 */
	private readonly int $num_ribbon_slots;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 *
	 * @param WP_Block $block Block instance.
	 */
	public function __construct( WP_Block $block ) {
		parent::__construct( $block );

		$this->week_days        = $this->get_context_array( 'gatherpress/weekDays' );
		$this->time_grid        = $this->get_time_grid();
		$this->num_ribbon_slots = $this->calculate_max_ribbon_slot() + 1;
	}

	/**
	 * Calculates the maximum ribbon slot index across all days in the week.
	 *
	 * @since TBD
	 *
	 * @return int Maximum slot index found or -1 if none.
	 */
	private function calculate_max_ribbon_slot(): int {
		$max_slot = -1;

		foreach ( $this->week_days as $day ) {
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
	 * @return string Rendered time gutter HTML.
	 */
	private function render_time_gutter(): string {
		$slot_labels = '';

		foreach ( $this->time_grid->slots as $slot_label ) {
			$slot_labels .= sprintf(
				'<div class="gatherpress-calendar__time-slot-label">%s</div>',
				esc_html( $slot_label )
			);
		}

		return sprintf(
			'<th scope="row" class="gatherpress-calendar__time-gutter" aria-hidden="true">%s</th>',
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
	 * @return string Rendered HTML <tr> row.
	 */
	public function render(): string {
		$week_number  = $this->get_context_int( 'gatherpress/weekNumber' );
		$day_template = Html_Renderer::get_inner_template_block( $this->block, Calendar_Day::BLOCK_NAME );
		$is_time_grid = $this->time_grid->enabled;

		$this->block->context['gatherpress/numRibbonSlots'] = $this->num_ribbon_slots;

		$days_html = $this->render_days( $day_template, (string) wp_date( Date_Range::DATE_FORMAT ) );

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
				'class'       => 'gatherpress-calendar__week' . ( $is_time_grid ? ' gatherpress--has-time-grid' : '' ),
				'style'       => $this->get_ribbon_rows_style(),
				'data-wp-key' => $this->get_row_key(),
			)
		);

		return sprintf(
			'<tr %1$s>%2$s%3$s</tr>',
			$wrapper_attributes,
			$is_time_grid ? $this->render_time_gutter() : '',
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
	 * @return string Inline style declarations.
	 */
	private function get_ribbon_rows_style(): string {
		return sprintf(
			'--gatherpress-num-ribbon-slots: %d; --gatherpress-ribbon-rows: %s;',
			$this->num_ribbon_slots,
			$this->num_ribbon_slots > 0 ? sprintf( 'repeat(%d, 1.5rem)', $this->num_ribbon_slots ) : 'initial'
		);
	}

	/**
	 * Count consecutive active (non-empty) columns remaining from the current column.
	 *
	 * @since TBD
	 *
	 * @param int $col_index Current column index.
	 *
	 * @return int Number of active columns remaining.
	 */
	private function count_remaining_active_columns( int $col_index ): int {
		$cols_left  = 0;
		$total_cols = count( $this->week_days );

		for ( $c = $col_index; $c < $total_cols; $c++ ) {
			$day_entry = $this->week_days[ $c ] ?? null;

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
	private function annotate_post_entry(
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
	 * @param array<mixed> $day_posts Posts on the day.
	 * @param int          $col_index Column index.
	 *
	 * @return array<mixed> Annotated day posts.
	 */
	private function annotate_day_posts( array $day_posts, int $col_index ): array {
		$prev_day     = $this->week_days[ $col_index - 1 ] ?? null;
		$next_day     = $this->week_days[ $col_index + 1 ] ?? null;
		$is_first_col = 0 === $col_index || ( is_array( $prev_day ) && ! empty( $prev_day['isEmpty'] ) );
		$is_last_col  = ( count( $this->week_days ) - 1 ) === $col_index
			|| ( is_array( $next_day ) && ! empty( $next_day['isEmpty'] ) );
		$cols_left    = $this->count_remaining_active_columns( $col_index );

		foreach ( $day_posts as $index => $post_entry ) {
			if ( is_array( $post_entry ) ) {
				$day_posts[ $index ] = $this->annotate_post_entry(
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
	 * @param array<mixed> $day       Day entry data.
	 * @param array<mixed> $day_posts Annotated day posts.
	 * @param string       $today     Today's Y-m-d date string.
	 *
	 * @return array<string, mixed> Day block context.
	 */
	private function build_day_context( array $day, array $day_posts, string $today ): array {
		$day_date = is_string( $day['date'] ?? null ) ? $day['date'] : '';

		return array_merge(
			$this->block->context,
			array(
				'gatherpress/dayDate'        => $day_date,
				'gatherpress/dayNumber'      => $day['day'] ?? 0,
				'gatherpress/dayPosts'       => $day_posts,
				'gatherpress/isEmpty'        => ! empty( $day['isEmpty'] ),
				'gatherpress/isToday'        => '' !== $day_date && $day_date === $today,
				'gatherpress/weekday'        => $day['weekday'] ?? '',
				'gatherpress/isWeekend'      => ! empty( $day['isWeekend'] ),
				'gatherpress/numRibbonSlots' => $this->num_ribbon_slots,
			)
		);
	}

	/**
	 * Render all day blocks for a given week.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $day_template Parsed day template block.
	 * @param string               $today        Today's date in Y-m-d format.
	 * @phpstan-param TemplateBlock $day_template
	 *
	 * @return string Rendered HTML of all day cells.
	 */
	private function render_days( array $day_template, string $today ): string {
		$output = '';

		foreach ( $this->week_days as $col_index => $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}

			$day_posts   = is_array( $day['posts'] ?? null ) ? $day['posts'] : array();
			$annotated   = $this->annotate_day_posts( $day_posts, (int) $col_index );
			$day_context = $this->build_day_context( $day, $annotated, $today );
			$output     .= ( new WP_Block( $day_template, $day_context ) )->render();
		}

		return $output;
	}

	/**
	 * Build unique key attribute for the week row.
	 *
	 * @since TBD
	 *
	 * @return string Row key.
	 */
	private function get_row_key(): string {
		return sprintf(
			'week-%d-%d-%d',
			$this->get_context_int( 'gatherpress/year' ),
			$this->get_context_int( 'gatherpress/month' ),
			$this->get_context_int( 'gatherpress/weekIndex' )
		);
	}
}
