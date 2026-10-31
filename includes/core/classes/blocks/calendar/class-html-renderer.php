<?php
/**
 * Renders the markup of the Calendar block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar_Week;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Utility;
use WP_Block;

/**
 * Class Html_Renderer.
 *
 * Generates the HTML output of the calendar: the wrapper, one table per unit, and the week rows,
 * which the gatherpress/calendar-week blocks render.
 *
 * @since TBD
 *
 * @phpstan-import-type CalendarUnit from \GatherPress\Core\Calendar\Structure_Builder
 * @phpstan-type CalendarData array{
 *     heading: string,
 *     day_names: list<string>,
 *     weeks: list<array<int, array<string, mixed>>>,
 *     view_type: string,
 *     unit_count: int,
 *     units: non-empty-list<CalendarUnit>
 * }
 * @phpstan-type TemplateBlock array{
 *     blockName?: string|null,
 *     attrs?: array<string, mixed>,
 *     innerBlocks?: array<mixed>,
 *     innerHTML?: string,
 *     innerContent?: array<mixed>
 * }
 */
final class Html_Renderer {

	/**
	 * The gatherpress/calendar block instance.
	 *
	 * @since TBD
	 * @var WP_Block
	 */
	private WP_Block $block;

	/**
	 * The date range of the calendar.
	 *
	 * @since TBD
	 * @var Date_Range
	 */
	private Date_Range $date_range;

	/**
	 * The time grid of the calendar.
	 *
	 * @since TBD
	 * @var Time_Grid
	 */
	private Time_Grid $time_grid;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param WP_Block   $block      The gatherpress/calendar block instance.
	 * @param Date_Range $date_range The date range of the calendar.
	 * @param Time_Grid  $time_grid  The time grid of the calendar.
	 */
	public function __construct( WP_Block $block, Date_Range $date_range, Time_Grid $time_grid ) {
		$this->block      = $block;
		$this->date_range = $date_range;
		$this->time_grid  = $time_grid;
	}

	/**
	 * Build outer wrapper block attributes.
	 *
	 * @since TBD
	 *
	 * @param string $view_type    View type.
	 * @param int    $unit_count   Number of units.
	 * @param bool   $is_time_grid Whether time grid is enabled.
	 *
	 * @return string HTML attribute string.
	 */
	private function build_wrapper_attributes( string $view_type, int $unit_count, bool $is_time_grid ): string {
		$classes = array( 'is-view-' . $view_type );

		if ( $unit_count > 1 ) {
			$classes[] = 'has-multiple-units';
		}

		if ( $is_time_grid ) {
			$classes[] = 'has-time-grid';
		}

		// A new key per date range makes client-side navigation mount a new
		// wrapper, so its init callback runs once for each new month. The
		// callback moves focus to the month heading (see the calendar's view.js).
		$start_date = $this->block->context['gatherpress/startDate'] ?? '';

		return get_block_wrapper_attributes(
			array(
				'class'                      => implode( ' ', $classes ),
				'style'                      => sprintf( '--gatherpress-calendar-units: %d;', $unit_count ),
				'data-wp-interactive'        => 'gatherpress/calendar',
				'data-wp-key'                => 'calendar-' . ( is_string( $start_date ) ? $start_date : '' ),
				'data-wp-init'               => 'callbacks.focusAfterNavigation',
				'data-wp-on-document--click' => 'actions.rememberPagination',
			)
		);
	}

	/**
	 * Build the inline style of a calendar table.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes    Block attributes.
	 * @param int                  $columns_count Number of table columns.
	 * @param int                  $slot_height   Height of one hour slot of the time grid, in pixels.
	 *
	 * @return string CSS declarations for the table's style attribute.
	 */
	private function build_table_style( array $attributes, int $columns_count, int $slot_height ): string {
		$style_processor = new Style_Processor( $attributes );
		$grid_gap        = $style_processor->get_block_gap_value();
		$column_gap      = $style_processor->get_block_column_gap_value();
		$table_styles    = array( sprintf( '--gatherpress-calendar-columns: %d', $columns_count ) );

		if ( '' !== $grid_gap ) {
			$table_styles[] = sprintf( 'gap: %s', $grid_gap );
		}

		// Only the column gap: day cells use it in calc() to stay square.
		if ( '' !== $column_gap ) {
			$table_styles[] = sprintf( '--gatherpress-calendar-column-gap: %s', $column_gap );
		}

		if ( $this->time_grid->enabled ) {
			$table_styles[] = sprintf( '--gatherpress-total-quarter-slots: %d', $this->time_grid->total_quarters );
			// The editor sets the same property from the day block's Slot Height control.
			$table_styles[] = sprintf( '--gatherpress-calendar-slot-height: %dpx', $slot_height );
		}

		return implode( '; ', $table_styles ) . ';';
	}

	/**
	 * Render HTML for an individual unit table.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $unit                Individual calendar unit data.
	 * @param string               $table_style         CSS declarations for the style attribute.
	 * @param bool                 $show_weekdays       Whether weekdays header is visible.
	 * @param bool                 $render_week_numbers Whether row headers for weeks are output.
	 * @param bool                 $is_time_grid        Whether time grid is enabled.
	 * @phpstan-param CalendarUnit $unit
	 *
	 * @return string Rendered table HTML.
	 */
	private function render_unit_table(
		array $unit,
		string $table_style,
		bool $show_weekdays,
		bool $render_week_numbers,
		bool $is_time_grid
	): string {
		// The template runs the weeks through wp_kses_post(). Widen the allowlist for this call only.
		add_filter( 'wp_kses_allowed_html', array( $this, 'allow_block_markup' ), 10, 2 );

		try {
			return Utility::render_template(
				sprintf( '%s/includes/templates/blocks/calendar/table.php', GATHERPRESS_CORE_PATH ),
				array(
					'table_class'   => 'gatherpress-calendar__table' . ( $is_time_grid ? ' has-time-grid' : '' ),
					'table_style'   => $table_style,
					'caption'       => $unit['caption'],
					'show_weekdays' => $show_weekdays,
					'header_cells'  => $this->render_header_cells(
						$unit['day_names'],
						$render_week_numbers,
						$is_time_grid
					),
					'weeks'         => $this->render_calendar_weeks( $unit['weeks'], $render_week_numbers ),
				)
			);
		} finally {
			remove_filter( 'wp_kses_allowed_html', array( $this, 'allow_block_markup' ) );
		}
	}

	/**
	 * Permit the markup of the blocks in a calendar through wp_kses_post().
	 *
	 * Added immediately before a calendar table renders and removed immediately after, so the
	 * widened allowlist never applies to anything else. Covers what wp_kses_post() would strip
	 * from the calendar's blocks: aria-modal on modals, the RSVP form and its fields, the forms
	 * and `form` attributes that open and close modals without JavaScript, SVG icons and embeds.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $tags    Allowed HTML tags and their attributes.
	 * @param string               $context The context the allowlist is being used in.
	 *
	 * @return array<string, mixed> Allowed tags, widened in the post context.
	 */
	public function allow_block_markup( array $tags, string $context ): array {
		if ( 'post' !== $context ) {
			return $tags;
		}

		$global_attributes = array(
			'aria-describedby' => true,
			'aria-hidden'      => true,
			'aria-label'       => true,
			'aria-labelledby'  => true,
			'class'            => true,
			'data-*'           => true,
			'hidden'           => true,
			'id'               => true,
			'role'             => true,
			'style'            => true,
			'title'            => true,
		);
		$field_attributes  = array_merge(
			$global_attributes,
			array(
				'autocomplete' => true,
				'disabled'     => true,
				'form'         => true,
				'name'         => true,
				'required'     => true,
			)
		);

		$tags['div']['aria-modal'] = true;
		$tags['button']            = array_merge( $tags['button'] ?? array(), $field_attributes );
		$tags['form']              = array_merge(
			$global_attributes,
			array(
				'action'     => true,
				'method'     => true,
				'novalidate' => true,
			)
		);
		$tags['input']             = array_merge(
			$field_attributes,
			array(
				'checked'     => true,
				'max'         => true,
				'maxlength'   => true,
				'min'         => true,
				'minlength'   => true,
				'pattern'     => true,
				'placeholder' => true,
				'step'        => true,
				'type'        => true,
				'value'       => true,
			)
		);
		$tags['select']            = array_merge( $field_attributes, array( 'multiple' => true ) );
		$tags['option']            = array(
			'disabled' => true,
			'selected' => true,
			'value'    => true,
		);
		$tags['textarea']          = array_merge(
			$tags['textarea'] ?? array(),
			$field_attributes,
			array(
				'maxlength'   => true,
				'placeholder' => true,
				'rows'        => true,
			)
		);
		$tags['svg']               = array_merge(
			$global_attributes,
			array(
				'focusable' => true,
				'height'    => true,
				'version'   => true,
				'viewbox'   => true,
				'width'     => true,
				'xmlns'     => true,
			)
		);
		$tags['path']              = array(
			'd'         => true,
			'fill'      => true,
			'fill-rule' => true,
		);
		$tags['iframe']            = array_merge(
			$global_attributes,
			array(
				'allow'           => true,
				'allowfullscreen' => true,
				'frameborder'     => true,
				'height'          => true,
				'loading'         => true,
				'referrerpolicy'  => true,
				'sandbox'         => true,
				'src'             => true,
				'width'           => true,
			)
		);

		return $tags;
	}

	/**
	 * Generate complete calendar HTML.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes    Block attributes.
	 * @param array<string, mixed> $calendar_data Calendar structure.
	 * @param int                  $slot_height   Height of one hour slot of the time grid, in pixels.
	 * @phpstan-param CalendarData $calendar_data
	 *
	 * @return string Calendar HTML.
	 */
	public function generate_calendar_html( array $attributes, array $calendar_data, int $slot_height = 48 ): string {
		$view_type     = $calendar_data['view_type'];
		$unit_count    = $calendar_data['unit_count'];
		$show_weekdays = is_bool( $attributes['showWeekdays'] ?? null ) ? $attributes['showWeekdays'] : true;
		$is_time_grid  = $this->time_grid->enabled;
		$columns_count = $this->date_range->get_columns_count( $is_time_grid );

		// Only views with several week rows need to tell the rows apart.
		$render_week_numbers = 'month' === $view_type || ( 'week' === $view_type && $unit_count > 1 );
		$table_style         = $this->build_table_style( $attributes, $columns_count, $slot_height );
		$tables              = '';

		foreach ( $calendar_data['units'] as $unit ) {
			$tables .= $this->render_unit_table(
				$unit,
				$table_style,
				$show_weekdays,
				$render_week_numbers,
				$is_time_grid
			);
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			$this->build_wrapper_attributes( $view_type, $unit_count, $is_time_grid ),
			$tables
		);
	}

	/**
	 * Render the column header cells of a table.
	 *
	 * @since TBD
	 *
	 * @param string[] $day_names           Weekday names.
	 * @param bool     $render_week_numbers Whether rows get a week number header.
	 * @param bool     $is_time_grid        Whether time grid is active.
	 *
	 * @return string Escaped header cells HTML.
	 */
	private function render_header_cells( array $day_names, bool $render_week_numbers, bool $is_time_grid ): string {
		$cells = '';

		// Prepend empty header for the time gutter column.
		if ( $is_time_grid ) {
			$cells .= '<th scope="col" class="gatherpress-calendar__time-gutter-header" aria-hidden="true"></th>';
		}

		// Header for the hidden week number column, so the row headers line up.
		if ( $render_week_numbers ) {
			$cells .= sprintf(
				'<th scope="col" class="gatherpress--screen-reader-text">%s</th>',
				esc_html_x( 'Week', 'week number column header', 'gatherpress' )
			);
		}

		foreach ( $day_names as $day_name ) {
			$cells .= sprintf( '<th scope="col">%s</th>', esc_html( $day_name ) );
		}

		return $cells;
	}

	/**
	 * Render calendar weeks by delegating to gatherpress/calendar-week blocks.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<int, array<string, mixed>>> $weeks               Weeks array.
	 * @param bool                                         $render_week_numbers Whether rows get a week number header.
	 *
	 * @return string Weeks HTML, as the week blocks render it.
	 */
	private function render_calendar_weeks( array $weeks, bool $render_week_numbers ): string {
		$week_template = self::get_inner_template_block( $this->block, Calendar_Week::BLOCK_NAME );
		$html          = '';

		foreach ( $weeks as $week_index => $week_days ) {
			$week_number  = $render_week_numbers ? $this->date_range->week->get_number( $week_days ) : 0;
			$week_context = array_merge(
				$this->block->context,
				array(
					'gatherpress/weekIndex'  => $week_index,
					'gatherpress/weekDays'   => $week_days,
					'gatherpress/weekNumber' => $week_number,
				)
			);

			$html .= ( new WP_Block( $week_template, $week_context ) )->render();
		}

		return $html;
	}

	/**
	 * Locate the inner template block by name within a block's innerBlocks,
	 * or generate a fallback structure.
	 *
	 * @since TBD
	 *
	 * @param WP_Block $block      Parent block instance.
	 * @param string   $block_name Target inner block name.
	 *
	 * @return TemplateBlock Parsed template block.
	 */
	public static function get_inner_template_block( WP_Block $block, string $block_name ): array {
		$parsed       = $block->parsed_block;
		$inner_blocks = is_array( $parsed['innerBlocks'] ?? null ) ? $parsed['innerBlocks'] : array();

		$inner_block = Utility::find_block( $inner_blocks, $block_name );

		if ( null !== $inner_block ) {
			/**
			 * A parsed block.
			 *
			 * @var TemplateBlock $inner_block
			 */
			return $inner_block;
		}

		return array(
			'blockName'    => $block_name,
			'attrs'        => array(),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => is_string( $parsed['innerHTML'] ?? null ) ? $parsed['innerHTML'] : '',
			'innerContent' => is_array( $parsed['innerContent'] ?? null ) ? $parsed['innerContent'] : array(),
		);
	}
}
