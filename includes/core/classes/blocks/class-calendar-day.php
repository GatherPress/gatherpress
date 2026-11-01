<?php
/**
 * Renders the Calendar Day block.
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
use GatherPress\Core\Calendar\Week;
use GatherPress\Core\Utility;
use WP_Block;

/**
 * Class Calendar_Day.
 *
 * Renders one table cell of the calendar: the day number and the calendar entries of the day.
 *
 * @since TBD
 *
 * @phpstan-import-type TemplateBlock from Html_Renderer
 */
final class Calendar_Day extends Base {

	/**
	 * Constant representing the Block Name.
	 *
	 * @since TBD
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/calendar-day';

	/**
	 * Render the calendar day cell.
	 *
	 * Called from the block's render.php.
	 *
	 * @since TBD
	 *
	 * @return string Rendered HTML, or an empty string outside a calendar week.
	 */
	public function render(): string {
		if ( ! isset( $this->block->context['gatherpress/isEmpty'] ) ) {
			return '';
		}

		$is_empty     = ! empty( $this->block->context['gatherpress/isEmpty'] );
		$day_number   = $this->get_context_int( 'gatherpress/dayNumber' );
		$day_date     = $this->get_context_string( 'gatherpress/dayDate' );
		$weekday      = $this->get_context_string( 'gatherpress/weekday' );
		$cell_content = '';

		if ( ! $is_empty && $day_number > 0 ) {
			$inner_blocks = $this->find_day_inner_blocks();
			$cell_content = $this->render_day_cell_content( $inner_blocks['day_number'], $inner_blocks['entries'] );
		}

		$classes = array_merge( $this->get_day_cell_classes(), $this->get_time_grid_classes() );

		return sprintf(
			'<td role="cell" %1$s>%2$s</td>',
			get_block_wrapper_attributes( $this->build_cell_attributes( $classes, $day_date, $is_empty, $weekday ) ),
			$cell_content
		);
	}

	/**
	 * Get the Ribbon style class that the time grid needs.
	 *
	 * The time grid places its entries with the Ribbon layout, so a day in a time grid gets the
	 * Ribbon class whichever style it has, as in the editor (calendar-day/edit.js).
	 *
	 * @since TBD
	 *
	 * @return list<string> The Ribbon class, or nothing when it is not needed.
	 */
	private function get_time_grid_classes(): array {
		$class_name = $this->block->attributes['className'] ?? '';

		if ( ! $this->get_time_grid()->enabled ) {
			return array();
		}

		// The author picked the Ribbon style: get_block_wrapper_attributes() adds its class.
		if ( is_string( $class_name ) && preg_match( '/(^|\s)is-style-ribbon(\s|$)/', $class_name ) ) {
			return array();
		}

		return array( 'is-style-ribbon' );
	}

	/**
	 * Build cell attributes including key and interactivity directives.
	 *
	 * @since TBD
	 *
	 * @param string[] $classes  CSS classes list.
	 * @param string   $day_date YYYY-MM-DD date string.
	 * @param bool     $is_empty Whether the cell is empty padding.
	 * @param string   $weekday  Weekday slug.
	 *
	 * @return array<string, string> Attributes for wrapper.
	 */
	private function build_cell_attributes(
		array $classes,
		string $day_date,
		bool $is_empty,
		string $weekday
	): array {
		$cell_key = ! $is_empty && '' !== $day_date
			? 'day-' . $day_date
			: 'empty-' . ( '' !== $weekday ? $weekday : uniqid() );

		$attributes = array(
			'class'       => implode( ' ', $classes ),
			'data-wp-key' => $cell_key,
		);

		if ( $is_empty || '' === $day_date ) {
			return $attributes;
		}

		if ( (string) wp_date( Date_Range::DATE_FORMAT ) === $day_date ) {
			$attributes['aria-current'] = 'date';
		}

		self::register_day_state();

		$attributes['data-wp-interactive']                   = 'gatherpress/calendar-day';
		$attributes['data-wp-context']                       = (string) wp_json_encode( array( 'date' => $day_date ) );
		$attributes['data-wp-class--gatherpress--is-today']  = 'state.isToday';
		$attributes['data-wp-class--gatherpress--is-past']   = 'state.isPast';
		$attributes['data-wp-class--gatherpress--is-future'] = 'state.isFuture';
		$attributes['data-wp-bind--aria-current']            = 'state.ariaCurrent';

		return $attributes;
	}

	/**
	 * Register the server values of the day state that the cell directives read.
	 *
	 * The server processes the directives of the cell when it renders the page. Without these
	 * values it would remove the gatherpress--is-today, gatherpress--is-past and
	 * gatherpress--is-future classes and aria-current that PHP set. The server uses the site's
	 * date; the view script then uses the visitor's date (calendar-day/view.js), which can be
	 * another day near midnight.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private static function register_day_state(): void {
		wp_interactivity_state(
			'gatherpress/calendar-day',
			array(
				'isToday'     => static function (): bool {
					return 0 === self::compare_context_date();
				},
				'isPast'      => static function (): bool {
					return -1 === self::compare_context_date();
				},
				'isFuture'    => static function (): bool {
					return 1 === self::compare_context_date();
				},
				'ariaCurrent' => static function () {
					return 0 === self::compare_context_date() ? 'date' : false;
				},
			)
		);
	}

	/**
	 * Compare the date of the cell that the server processes now with today's date.
	 *
	 * @since TBD
	 *
	 * @return int|null -1 for a past day, 0 for today, 1 for a future day, null without a date.
	 */
	private static function compare_context_date(): ?int {
		$context = wp_interactivity_get_context( 'gatherpress/calendar-day' );
		$date    = is_string( $context['date'] ?? null ) ? $context['date'] : '';

		return '' === $date ? null : $date <=> (string) wp_date( Date_Range::DATE_FORMAT );
	}

	/**
	 * Check if any post entry is a multi-day or all-day event.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $day_posts List of day post entries.
	 *
	 * @return bool True if at least one entry spans multiple days or is all day.
	 */
	private function has_multi_day_event( array $day_posts ): bool {
		foreach ( $day_posts as $post_entry ) {
			if ( ! is_array( $post_entry ) ) {
				continue;
			}

			$total_days = is_numeric( $post_entry['total_days'] ?? null ) ? (int) $post_entry['total_days'] : 1;

			if ( $total_days > 1 || ! empty( $post_entry['is_all_day'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve class names for a calendar day cell.
	 *
	 * @since TBD
	 *
	 * @return string[] Array of CSS classes.
	 */
	private function get_day_cell_classes(): array {
		$is_empty  = ! empty( $this->block->context['gatherpress/isEmpty'] );
		$classes   = array( 'gatherpress-calendar__day' );
		$day_posts = $this->get_context_array( 'gatherpress/dayPosts' );

		if ( $is_empty ) {
			$classes[] = 'gatherpress--is-empty';
		}

		if ( ! empty( $day_posts ) ) {
			$classes[] = 'gatherpress--has-posts';

			if ( $this->has_multi_day_event( $day_posts ) ) {
				$classes[] = 'gatherpress--has-multi-day-event';
			}
		}

		list( $weekday, $is_weekend ) = $this->resolve_weekday_and_weekend();

		if ( $is_weekend ) {
			$classes[] = 'gatherpress--is-weekend';
		}

		if ( '' !== $weekday ) {
			$classes[] = 'gatherpress--is-' . sanitize_html_class( strtolower( $weekday ) );
		}

		if ( ! $is_empty ) {
			$day_date = $this->get_context_string( 'gatherpress/dayDate' );
			$classes  = array_merge( $classes, $this->get_temporal_classes( $day_date ) );
		}

		return $classes;
	}

	/**
	 * Resolve weekday slug and weekend status.
	 *
	 * @since TBD
	 *
	 * @return array{0: string, 1: bool} Weekday slug and is_weekend flag.
	 */
	private function resolve_weekday_and_weekend(): array {
		$weekday    = $this->get_context_string( 'gatherpress/weekday' );
		$is_weekend = ! empty( $this->block->context['gatherpress/isWeekend'] );
		$day_date   = $this->get_context_string( 'gatherpress/dayDate' );

		if ( '' === $weekday && '' !== $day_date ) {
			$dow        = (int) gmdate( 'w', (int) strtotime( $day_date ) );
			$weekday    = Week::DAY_SLUGS[ $dow ];
			$is_weekend = ( new Week() )->is_weekend( $dow );
		}

		return array( $weekday, $is_weekend );
	}

	/**
	 * Resolve temporal CSS classes (today, past, future).
	 *
	 * @since TBD
	 *
	 * @param string $day_date YYYY-MM-DD date string.
	 *
	 * @return list<string> Temporal classes.
	 */
	private function get_temporal_classes( string $day_date ): array {
		if ( '' === $day_date ) {
			return array();
		}

		$today = (string) wp_date( Date_Range::DATE_FORMAT );

		if ( $day_date === $today ) {
			return array( 'gatherpress--is-today' );
		}

		return $day_date < $today
			? array( 'gatherpress--is-past' )
			: array( 'gatherpress--is-future' );
	}

	/**
	 * Check if a block is a day number block with the gatherpress/calendar-day binding.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $block Parsed block array.
	 *
	 * @return bool True if day number binding block.
	 */
	private function is_day_number_block( array $block ): bool {
		$attrs    = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$metadata = is_array( $attrs['metadata'] ?? null ) ? $attrs['metadata'] : array();
		$bindings = is_array( $metadata['bindings'] ?? null ) ? $metadata['bindings'] : array();
		$content  = is_array( $bindings['content'] ?? null ) ? $bindings['content'] : array();

		return self::BLOCK_NAME === ( $content['source'] ?? '' );
	}

	/**
	 * Locate day number and entries inner blocks.
	 *
	 * @since TBD
	 *
	 * @return array{day_number: TemplateBlock|null, entries: TemplateBlock|null} Found blocks.
	 */
	private function find_day_inner_blocks(): array {
		$inner_blocks     = is_array( $this->block->parsed_block['innerBlocks'] ?? null )
			? $this->block->parsed_block['innerBlocks']
			: array();
		$day_number_block = null;

		foreach ( $inner_blocks as $inner ) {
			if ( is_array( $inner ) && $this->is_day_number_block( $inner ) ) {
				$day_number_block = $inner;
				break;
			}
		}

		/**
		 * Parsed blocks.
		 *
		 * @var TemplateBlock|null $day_number_block
		 * @var TemplateBlock|null $entries_block
		 */
		$entries_block = Utility::find_block( $inner_blocks, Calendar_Entries::BLOCK_NAME );

		return array(
			'day_number' => $day_number_block,
			'entries'    => $entries_block,
		);
	}

	/**
	 * Render day cell content (day number and events list).
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>|null $day_number_block Bound day number block.
	 * @param array<string, mixed>|null $entries_block    Calendar entries block.
	 * @phpstan-param TemplateBlock|null $day_number_block
	 * @phpstan-param TemplateBlock|null $entries_block
	 *
	 * @return string Rendered HTML.
	 */
	private function render_day_cell_content( ?array $day_number_block, ?array $entries_block ): string {
		$day_number      = $this->get_context_int( 'gatherpress/dayNumber' );
		$day_number_html = null !== $day_number_block
			? ( new WP_Block( $day_number_block, $this->block->context ) )->render()
			: sprintf( '<p class="gatherpress-calendar__day-number">%s</p>', esc_html( (string) $day_number ) );
		$entries_html    = null !== $entries_block
			? ( new WP_Block( $entries_block, $this->block->context ) )->render()
			: '';

		return $day_number_html . $entries_html;
	}
}
