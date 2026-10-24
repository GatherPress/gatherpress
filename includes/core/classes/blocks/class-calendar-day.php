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

use GatherPress\Core\Blocks\Calendar\Date_Calculator;
use GatherPress\Core\Blocks\Calendar\Html_Renderer;
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
final class Calendar_Day {

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
	 * @param WP_Block $block Block instance.
	 *
	 * @return string Rendered HTML, or an empty string outside a calendar week.
	 */
	public static function render( WP_Block $block ): string {
		$context = $block->context;

		if ( ! isset( $context['gatherpress/isEmpty'] ) ) {
			return '';
		}

		$is_empty     = ! empty( $context['gatherpress/isEmpty'] );
		$day_number   = self::get_day_number( $context );
		$day_date     = is_string( $context['gatherpress/dayDate'] ?? null ) ? $context['gatherpress/dayDate'] : '';
		$weekday      = is_string( $context['gatherpress/weekday'] ?? null ) ? $context['gatherpress/weekday'] : '';
		$cell_content = '';

		if ( ! $is_empty && $day_number > 0 ) {
			$inner_blocks = self::find_day_inner_blocks(
				is_array( $block->parsed_block['innerBlocks'] ?? null ) ? $block->parsed_block['innerBlocks'] : array()
			);
			$cell_content = self::render_day_cell_content(
				$context,
				$inner_blocks['day_number'],
				$inner_blocks['entries']
			);
		}

		$classes = array_merge( self::get_day_cell_classes( $context ), self::get_time_grid_classes( $block ) );

		return sprintf(
			'<td %1$s>%2$s</td>',
			get_block_wrapper_attributes( self::build_cell_attributes( $classes, $day_date, $is_empty, $weekday ) ),
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
	 * @param WP_Block $block Day block instance.
	 *
	 * @return list<string> The Ribbon class, or nothing when it is not needed.
	 */
	private static function get_time_grid_classes( WP_Block $block ): array {
		$grid_config = $block->context['gatherpress/timeGridConfig'] ?? null;
		$class_name  = $block->attributes['className'] ?? '';

		if ( ! is_array( $grid_config ) || empty( $grid_config['enabled'] ) ) {
			return array();
		}

		// The author picked the Ribbon style: get_block_wrapper_attributes() adds its class.
		if ( is_string( $class_name ) && preg_match( '/(^|\s)is-style-ribbon(\s|$)/', $class_name ) ) {
			return array();
		}

		return array( 'is-style-ribbon' );
	}

	/**
	 * Get the day of the month from the day context.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $context Day context.
	 *
	 * @return int Day of the month, or 0 when the context has none.
	 */
	private static function get_day_number( array $context ): int {
		return is_numeric( $context['gatherpress/dayNumber'] ?? null ) ? (int) $context['gatherpress/dayNumber'] : 0;
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
	private static function build_cell_attributes(
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

		if ( Date_Calculator::get_today() === $day_date ) {
			$attributes['aria-current'] = 'date';
		}

		self::register_day_state();

		$attributes['data-wp-interactive']        = 'gatherpress/calendar-day';
		$attributes['data-wp-context']            = (string) wp_json_encode( array( 'date' => $day_date ) );
		$attributes['data-wp-class--is-today']    = 'state.isToday';
		$attributes['data-wp-class--is-past']     = 'state.isPast';
		$attributes['data-wp-class--is-future']   = 'state.isFuture';
		$attributes['data-wp-bind--aria-current'] = 'state.ariaCurrent';

		return $attributes;
	}

	/**
	 * Register the server values of the day state that the cell directives read.
	 *
	 * The server processes the directives of the cell when it renders the page. Without these
	 * values it would remove the is-today, is-past and is-future classes and aria-current that
	 * PHP set. The server uses the site's date; the view script then uses the visitor's date
	 * (calendar-day/view.js), which can be another day near midnight.
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

		return '' === $date ? null : $date <=> Date_Calculator::get_today();
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
	private static function has_multi_day_event( array $day_posts ): bool {
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
	 * @param array<mixed> $context Block context.
	 *
	 * @return string[] Array of CSS classes.
	 */
	private static function get_day_cell_classes( array $context ): array {
		$classes   = array( 'gatherpress-calendar__day' );
		$day_posts = $context['gatherpress/dayPosts'] ?? null;

		if ( ! empty( $context['gatherpress/isEmpty'] ) ) {
			$classes[] = 'is-empty';
		}

		if ( is_array( $day_posts ) && ! empty( $day_posts ) ) {
			$classes[] = 'has-posts';

			if ( self::has_multi_day_event( $day_posts ) ) {
				$classes[] = 'has-multi-day-event';
			}
		}

		list( $weekday, $is_weekend ) = self::resolve_weekday_and_weekend( $context );

		if ( $is_weekend ) {
			$classes[] = 'is-weekend';
		}

		if ( '' !== $weekday ) {
			$classes[] = 'is-' . sanitize_html_class( strtolower( $weekday ) );
		}

		if ( empty( $context['gatherpress/isEmpty'] ) ) {
			$day_date = is_string( $context['gatherpress/dayDate'] ?? null ) ? $context['gatherpress/dayDate'] : '';
			$classes  = array_merge( $classes, self::get_temporal_classes( $day_date ) );
		}

		return $classes;
	}

	/**
	 * Resolve weekday slug and weekend status.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $context Block context.
	 *
	 * @return array{0: string, 1: bool} Weekday slug and is_weekend flag.
	 */
	private static function resolve_weekday_and_weekend( array $context ): array {
		$weekday    = is_string( $context['gatherpress/weekday'] ?? null ) ? $context['gatherpress/weekday'] : '';
		$is_weekend = ! empty( $context['gatherpress/isWeekend'] );
		$day_date   = is_string( $context['gatherpress/dayDate'] ?? null ) ? $context['gatherpress/dayDate'] : '';

		if ( '' === $weekday && '' !== $day_date ) {
			$dow        = (int) gmdate( 'w', (int) strtotime( $day_date ) );
			$weekday    = Date_Calculator::get_weekday_slug( $dow );
			$is_weekend = Date_Calculator::is_weekend_day( $dow );
		}

		return array( $weekday, $is_weekend );
	}

	/**
	 * Resolve temporal CSS classes (is-today, past, future).
	 *
	 * @since TBD
	 *
	 * @param string $day_date YYYY-MM-DD date string.
	 *
	 * @return list<string> Temporal classes.
	 */
	private static function get_temporal_classes( string $day_date ): array {
		if ( '' === $day_date ) {
			return array();
		}

		$today = Date_Calculator::get_today();

		if ( $day_date === $today ) {
			return array( 'is-today' );
		}

		return $day_date < $today
			? array( 'is-past', 'past' )
			: array( 'is-future', 'future' );
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
	private static function is_day_number_block( array $block ): bool {
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
	 * @param array<mixed> $inner_blocks Parsed inner blocks.
	 *
	 * @return array{day_number: TemplateBlock|null, entries: TemplateBlock|null} Found blocks.
	 */
	private static function find_day_inner_blocks( array $inner_blocks ): array {
		$day_number_block = null;
		$entries_block    = null;

		foreach ( $inner_blocks as $inner ) {
			if ( ! is_array( $inner ) ) {
				continue;
			}

			/**
			 * A parsed block.
			 *
			 * @var TemplateBlock $inner
			 */
			if ( null === $day_number_block && self::is_day_number_block( $inner ) ) {
				$day_number_block = $inner;
			}

			if ( null === $entries_block && Calendar_Entries::BLOCK_NAME === ( $inner['blockName'] ?? '' ) ) {
				$entries_block = $inner;
			}
		}

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
	 * @param array<string, mixed>      $context          Day context.
	 * @param array<string, mixed>|null $day_number_block Bound day number block.
	 * @param array<string, mixed>|null $entries_block    Calendar entries block.
	 * @phpstan-param TemplateBlock|null $day_number_block
	 * @phpstan-param TemplateBlock|null $entries_block
	 *
	 * @return string Rendered HTML.
	 */
	private static function render_day_cell_content(
		array $context,
		?array $day_number_block,
		?array $entries_block
	): string {
		$day_number = self::get_day_number( $context );

		$day_number_html = null !== $day_number_block
			? ( new WP_Block( $day_number_block, $context ) )->render()
			: sprintf( '<p class="gatherpress-calendar__day-number">%s</p>', esc_html( (string) $day_number ) );
		$entries_html    = null !== $entries_block ? ( new WP_Block( $entries_block, $context ) )->render() : '';

		return $day_number_html . $entries_html;
	}
}
