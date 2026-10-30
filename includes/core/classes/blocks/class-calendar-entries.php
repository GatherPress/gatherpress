<?php
/**
 * Renders the Calendar Entries block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar\Style_Processor;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Traits\Singleton;
use WP_Block;
use WP_Post;

/**
 * Class Calendar_Entries.
 *
 * Renders the entries of one calendar day: the entry template once for each post of the day,
 * with that post's context. Its filters are added while an entry renders and removed after it.
 *
 * @since TBD
 */
final class Calendar_Entries {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since TBD
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/calendar-entries';

	/**
	 * Class that hides text on screen but keeps it for screen readers.
	 *
	 * The same class as General_Block::SCREEN_READER_CLASS.
	 *
	 * @since TBD
	 * @var string
	 */
	const SCREEN_READER_CLASS = 'gatherpress--screen-reader-text';

	/**
	 * ID of the event whose entry renders now; 0 between entries.
	 *
	 * @since TBD
	 * @var int
	 */
	private int $current_post_id = 0;

	/**
	 * Post type of the event whose entry renders now; '' between entries.
	 *
	 * @since TBD
	 * @var string
	 */
	private string $current_post_type = '';

	/**
	 * Span data of the event whose entry renders now; empty between entries.
	 *
	 * @since TBD
	 * @var array<string, mixed>
	 */
	private array $current_span_data = array();

	/**
	 * Whether the template of the entry that renders now shows a Post Title outside the modal.
	 *
	 * @since TBD
	 * @var bool
	 */
	private bool $current_shows_post_title = false;

	/**
	 * Current modal nesting depth to distinguish triggers from modal-content.
	 *
	 * @since TBD
	 * @var int
	 */
	private int $modal_depth = 0;

	/**
	 * Number of entry templates that render now, more than 1 for a calendar inside an entry.
	 *
	 * @since TBD
	 * @var int
	 */
	private int $render_depth = 0;

	/**
	 * Class constructor.
	 *
	 * The block needs no hooks: `render.php` calls {@see self::render()}
	 * directly, and the entry filters are added only while an entry renders.
	 *
	 * @since TBD
	 */
	protected function __construct() {
	}

	/**
	 * Render the calendar entries of one day.
	 *
	 * Called from the block's render.php.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param WP_Block             $block      Block instance.
	 *
	 * @return string Rendered HTML.
	 */
	public function render( array $attributes, WP_Block $block ): string {
		$day_posts    = is_array( $block->context['gatherpress/dayPosts'] ?? null )
			? $block->context['gatherpress/dayPosts']
			: array();
		$time_grid    = $block->context['gatherpress/timeGridConfig'] ?? null;
		$is_time_grid = $time_grid instanceof Time_Grid && $time_grid->enabled;

		// A time grid keeps its day column even without entries.
		if ( empty( $day_posts ) && ! $is_time_grid ) {
			return '';
		}

		$grid_gap   = ( new Style_Processor( $attributes ) )->get_block_gap_value();
		$items_html = '';

		foreach ( $day_posts as $entry ) {
			$post_id = is_array( $entry ) ? ( $entry['id'] ?? 0 ) : $entry;

			/**
			 * Span data from Post_Organizer and Calendar_Week, keyed by name.
			 *
			 * @var array<string, mixed> $span_data
			 */
			$span_data = is_array( $entry ) ? $entry : array();

			if ( is_int( $post_id ) && $post_id > 0 ) {
				$items_html .= $this->render_entry( $post_id, $block, $span_data );
			}
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => $is_time_grid ? 'has-time-grid' : '',
				'style' => '' !== $grid_gap ? sprintf( 'gap: %s', $grid_gap ) : '',
			)
		);

		return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $items_html );
	}

	/**
	 * Extract block attributes from parsed block safely.
	 *
	 * @since TBD
	 *
	 * @param WP_Block $block Block instance.
	 *
	 * @return array<string, mixed> Block attributes.
	 */
	private function get_block_attributes( WP_Block $block ): array {
		/**
		 * Parsed block attributes are keyed by attribute name.
		 *
		 * @var array<string, mixed> $attributes
		 */
		$attributes = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : array();

		return $attributes;
	}

	/**
	 * Build CSS custom properties for timed event placement.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $span_data Multi-day span descriptors.
	 *
	 * @return string CSS variable declarations string or empty string.
	 */
	private function build_timed_event_style( array $span_data ): string {
		if ( empty( $span_data['is_timed'] ) || ! isset( $span_data['time_row_start'], $span_data['time_row_end'] ) ) {
			return '';
		}

		return sprintf(
			'--gatherpress-event-time-row-start: %d; --gatherpress-event-time-row-end: %d; '
				. '--gatherpress-overlap-total: %d; --gatherpress-overlap-index: %d;',
			$this->get_int( $span_data, 'time_row_start', 0 ),
			$this->get_int( $span_data, 'time_row_end', 0 ),
			$this->get_int( $span_data, 'overlap_total', 1 ),
			$this->get_int( $span_data, 'overlap_index', 0 )
		);
	}

	/**
	 * Read an integer from span data.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $span_data Multi-day span descriptors.
	 * @param string               $key       Key to read.
	 * @param int                  $fallback  Value when the key is missing or not numeric.
	 *
	 * @return int The integer.
	 */
	private function get_int( array $span_data, string $key, int $fallback ): int {
		return is_numeric( $span_data[ $key ] ?? null ) ? (int) $span_data[ $key ] : $fallback;
	}

	/**
	 * Build combined style attribute for an entry wrapper.
	 *
	 * @since TBD
	 *
	 * @param string               $inline_styles Base inline styles from block attributes.
	 * @param array<string, mixed> $span_data     Span data.
	 * @param int                  $row_span      Row span count.
	 * @param string               $timed_style   Timed event style string.
	 *
	 * @return string Style attribute HTML string (e.g. ' style="..."').
	 */
	private function build_entry_style_attribute(
		string $inline_styles,
		array $span_data,
		int $row_span,
		string $timed_style
	): string {
		$slot_style = is_numeric( $span_data['slot'] ?? null )
			? sprintf( '--gatherpress-calendar-entry-slot: %d;', (int) $span_data['slot'] )
			: '';
		$styles     = array_filter(
			array(
				$inline_styles,
				$slot_style,
				sprintf( '--gatherpress-calendar-row-span: %d;', $row_span ),
				$timed_style,
			)
		);

		return sprintf( ' style="%s"', esc_attr( implode( ' ', $styles ) ) );
	}

	/**
	 * Render a single entry with its innerBlocks content.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id   Post ID.
	 * @param WP_Block             $block     The Calendar Entries block instance.
	 * @param array<string, mixed> $span_data Multi-day span descriptors.
	 *
	 * @return string Event HTML with innerBlocks rendered.
	 */
	private function render_entry( int $post_id, WP_Block $block, array $span_data ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$inner_content = $this->render_entry_inner_content( $post, $block, $span_data );
		$entry_styles  = $this->get_entry_styles_and_classes( $this->get_block_attributes( $block ) );
		$row_span      = $this->get_int( $span_data, 'row_span', 1 );
		$extra_classes = $this->build_span_classes( $span_data, $row_span );
		$timed_style   = $this->build_timed_event_style( $span_data );

		if ( '' !== $timed_style ) {
			$extra_classes[] = 'is-timed-event';
		}

		$classnames = trim( $entry_styles['classnames'] . ' ' . implode( ' ', $extra_classes ) );

		return sprintf(
			'<div class="%1$s"%2$s%3$s%4$s>%5$s</div>',
			esc_attr( implode( ' ', get_post_class( $classnames, $post_id ) ) ),
			$this->build_entry_style_attribute( $entry_styles['inline_styles'], $span_data, $row_span, $timed_style ),
			$this->build_entry_data_attributes( $span_data, $row_span ),
			$this->build_entry_iapi_directives( $post_id ),
			$inner_content
		);
	}

	/**
	 * Render entry inner template while managing global post state.
	 *
	 * @since TBD
	 *
	 * @param WP_Post              $post      Post object.
	 * @param WP_Block             $block     Calendar Entries block instance.
	 * @param array<string, mixed> $span_data Multi-day span descriptors.
	 *
	 * @return string Rendered inner content.
	 */
	private function render_entry_inner_content( WP_Post $post, WP_Block $block, array $span_data ): string {
		$original_post = ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) ? $GLOBALS['post'] : null;

		if ( isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		setup_postdata( $post );

		/**
		 * Parsed inner blocks of the entry template.
		 *
		 * @var array<int, array<string, mixed>> $inner_blocks
		 */
		$inner_blocks  = is_array( $block->parsed_block['innerBlocks'] ?? null )
			? $block->parsed_block['innerBlocks']
			: array();
		$inner_content = $this->render_entry_template( $inner_blocks, $post->ID, $post->post_type, $span_data );

		wp_reset_postdata();

		if ( isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $inner_content;
	}

	/**
	 * Build CSS class list from span data.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $span_data Multi-day span descriptors.
	 * @param int                  $row_span  Row span count.
	 *
	 * @return list<string> Extra CSS classes.
	 */
	private function build_span_classes( array $span_data, int $row_span ): array {
		$is_all_day = ! empty( $span_data['is_all_day'] );
		$classes    = array();

		if ( is_string( $span_data['span'] ?? null ) && '' !== $span_data['span'] ) {
			$classes[] = 'is-span-' . sanitize_html_class( $span_data['span'] );
		}

		if ( $is_all_day ) {
			$classes[] = 'is-all-day';
		}

		if ( $this->get_int( $span_data, 'total_days', 1 ) > 1 || $is_all_day ) {
			$classes[] = 'is-multi-day';
		}

		if ( ! empty( $span_data['is_continued'] ) ) {
			$classes[] = 'is-span-continued';
		}

		if ( ! empty( $span_data['is_continues'] ) ) {
			$classes[] = 'is-span-continues';
		}

		if ( $row_span >= 2 ) {
			$classes[] = 'has-multi-col-span';
		}

		return $classes;
	}

	/**
	 * Build data-* attributes for entry markup.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $span_data Multi-day span descriptors.
	 * @param int                  $row_span  Row span count.
	 *
	 * @return string Data attributes string.
	 */
	private function build_entry_data_attributes( array $span_data, int $row_span ): string {
		$attrs = '';

		if ( ! empty( $span_data['span'] ) && is_scalar( $span_data['span'] ) ) {
			$attrs .= sprintf( ' data-span="%s"', esc_attr( (string) $span_data['span'] ) );
		}

		foreach ( array( 'day_index', 'total_days', 'slot' ) as $key ) {
			if ( is_numeric( $span_data[ $key ] ?? null ) ) {
				$attrs .= sprintf( ' data-%s="%d"', str_replace( '_', '-', $key ), (int) $span_data[ $key ] );
			}
		}

		return $attrs . sprintf( ' data-row-span="%d"', $row_span );
	}

	/**
	 * Build Interactivity API directives for synchronized hover/focus.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string Directives string.
	 */
	private function build_entry_iapi_directives( int $post_id ): string {
		return ' data-wp-interactive="gatherpress/calendar"'
			. sprintf( ' data-wp-context="%s"', esc_attr( (string) wp_json_encode( array( 'eventId' => $post_id ) ) ) )
			. ' data-wp-on--mouseenter="actions.hoverEvent"'
			. ' data-wp-on--mouseleave="actions.unhoverEvent"'
			. ' data-wp-on--focusin="actions.focusEvent"'
			. ' data-wp-on--focusout="actions.unfocusEvent"'
			. ' data-wp-class--is-js-hovered="callbacks.isEventHovered"'
			. ' data-wp-class--is-js-focused="callbacks.isEventFocused"';
	}

	/**
	 * Check whether a block is a modal or the content of a modal.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $parsed_block Block data.
	 *
	 * @return bool True for gatherpress/modal and gatherpress/modal-content.
	 */
	private function is_modal_block( array $parsed_block ): bool {
		$modal_blocks = array( 'gatherpress/modal', 'gatherpress/modal-content' );

		return in_array( $parsed_block['blockName'] ?? '', $modal_blocks, true );
	}

	/**
	 * Track when modal rendering begins.
	 *
	 * @since TBD
	 *
	 * @param mixed                $pre_render   Pre-render value.
	 * @param array<string, mixed> $parsed_block Block data.
	 *
	 * @return mixed The pre-render value, unchanged.
	 */
	public function track_modal_depth_pre( $pre_render, array $parsed_block ) {
		if ( $this->is_modal_block( $parsed_block ) ) {
			++$this->modal_depth;
		}

		return $pre_render;
	}

	/**
	 * Track when modal rendering ends.
	 *
	 * @since TBD
	 *
	 * @param string               $block_content Rendered HTML.
	 * @param array<string, mixed> $parsed_block  Block data.
	 *
	 * @return string The rendered HTML, unchanged.
	 */
	public function track_modal_depth_post( string $block_content, array $parsed_block ): string {
		if ( $this->is_modal_block( $parsed_block ) && $this->modal_depth > 0 ) {
			--$this->modal_depth;
		}

		return $block_content;
	}

	/**
	 * Check whether the entry that renders now is a multi-day or all-day event.
	 *
	 * @since TBD
	 *
	 * @return bool True for a multi-day or all-day event.
	 */
	private function is_current_entry_multi_day(): bool {
		$total_days = $this->get_int( $this->current_span_data, 'total_days', 1 );

		return $total_days > 1 || ! empty( $this->current_span_data['is_all_day'] );
	}

	/**
	 * Suppress event-date block outside modal-content on multi-day events.
	 *
	 * Hooked only while an entry renders. It acts only when the template shows the Post Title
	 * outside the modal, so the title stays as the trigger. Inside modal-content the full date
	 * renders normally.
	 *
	 * @since TBD
	 *
	 * @param string $block_content Rendered block HTML.
	 *
	 * @return string The block HTML, or an empty string for a multi-day trigger.
	 */
	public function filter_multiday_event_date( string $block_content ): string {
		if ( ! $this->current_shows_post_title || $this->modal_depth > 0 || ! $this->is_current_entry_multi_day() ) {
			return $block_content;
		}

		return '';
	}

	/**
	 * Render the entry template for one event.
	 *
	 * While the template renders, the filters below give its blocks this
	 * event's context, a placeholder for an empty title, and, when the
	 * template shows no Post Title, the hidden title in the Event Date link.
	 *
	 * An entry template can hold a calendar, for example in its modal. The
	 * entries of that calendar render inside this entry. Each entry keeps the
	 * values of the entry around it and puts them back when it is done, and
	 * only the outermost entry adds and removes the filters. So the rest of
	 * the outer entry still has its event, its filters and its modal depth.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Parsed blocks of the entry template.
	 * @param int                              $post_id      Event post ID.
	 * @param string                           $post_type    Event post type.
	 * @param array<string, mixed>             $span_data    Multi-day span descriptors.
	 *
	 * @return string Rendered HTML.
	 */
	private function render_entry_template(
		array $inner_blocks,
		int $post_id,
		string $post_type,
		array $span_data
	): string {
		$previous_post_id          = $this->current_post_id;
		$previous_post_type        = $this->current_post_type;
		$previous_span_data        = $this->current_span_data;
		$previous_shows_post_title = $this->current_shows_post_title;
		$previous_modal_depth      = $this->modal_depth;

		$this->current_post_id          = $post_id;
		$this->current_post_type        = $post_type;
		$this->current_span_data        = $span_data;
		$this->current_shows_post_title = $this->template_shows_post_title( $inner_blocks );
		$this->modal_depth              = 0;

		if ( 0 === $this->render_depth ) {
			$this->add_entry_filters();
		}

		++$this->render_depth;

		// Finally: a callback or block that throws must not leave the filters or this entry's state.
		try {
			/**
			 * Fires before the calendar renders the entry template of one event.
			 *
			 * The calendar has added its filters for this event. Callbacks can
			 * add their own filters, or remove one of the calendar's, for example:
			 * remove_filter( 'the_title', array( Calendar_Entries::get_instance(), 'filter_empty_title' ), 10 );
			 * The calendar removes its own filters after the entry renders. For a
			 * calendar inside an entry template, that is after the outer entry. The
			 * action fires once for each entry, so a multi-day event fires it once
			 * for each of its visible days.
			 *
			 * @since TBD
			 *
			 * @param int                              $post_id      Event post ID.
			 * @param string                           $post_type    Event post type.
			 * @param array<int, array<string, mixed>> $inner_blocks Parsed blocks of the entry template.
			 * @param array<string, mixed>             $span_data    Multi-day span descriptors.
			 */
			do_action( 'gatherpress_calendar_entry_render', $post_id, $post_type, $inner_blocks, $span_data );

			return $this->render_template( $inner_blocks );
		} finally {
			--$this->render_depth;

			if ( 0 === $this->render_depth ) {
				$this->remove_entry_filters();
			}

			$this->current_post_id          = $previous_post_id;
			$this->current_post_type        = $previous_post_type;
			$this->current_span_data        = $previous_span_data;
			$this->current_shows_post_title = $previous_shows_post_title;
			$this->modal_depth              = $previous_modal_depth;
		}
	}

	/**
	 * Add the filters that change the blocks of an entry template.
	 *
	 * Both Event Date filters are added. With the Post Title as the trigger, a multi-day ribbon
	 * needs no time. Without it, the Event Date link is the trigger and must stay, and it gets
	 * the hidden title. Each filter checks the template of the entry that renders now.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function add_entry_filters(): void {
		// PHP_INT_MIN: set the event's context first, so later context filters can read or change it.
		add_filter( 'render_block_context', array( $this, 'filter_entry_context' ), PHP_INT_MIN );
		add_filter( 'the_title', array( $this, 'filter_empty_title' ), 10, 2 );
		add_filter( 'pre_render_block', array( $this, 'track_modal_depth_pre' ), 10, 2 );
		// PHP_INT_MAX: the modal depth drops only after every other render_block filter runs.
		add_filter( 'render_block', array( $this, 'track_modal_depth_post' ), PHP_INT_MAX, 2 );
		add_filter( 'render_block_core/post-title', array( $this, 'filter_post_title_span' ) );
		add_filter( 'render_block_gatherpress/event-date', array( $this, 'filter_multiday_event_date' ) );
		add_filter( 'render_block_gatherpress/event-date', array( $this, 'filter_event_date_link' ), 10, 2 );
	}

	/**
	 * Remove the filters that add_entry_filters() added.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function remove_entry_filters(): void {
		remove_filter( 'render_block_gatherpress/event-date', array( $this, 'filter_event_date_link' ), 10 );
		remove_filter( 'render_block_gatherpress/event-date', array( $this, 'filter_multiday_event_date' ), 10 );
		remove_filter( 'render_block_core/post-title', array( $this, 'filter_post_title_span' ), 10 );
		remove_filter( 'render_block', array( $this, 'track_modal_depth_post' ), PHP_INT_MAX );
		remove_filter( 'pre_render_block', array( $this, 'track_modal_depth_pre' ), 10 );
		remove_filter( 'the_title', array( $this, 'filter_empty_title' ), 10 );
		remove_filter( 'render_block_context', array( $this, 'filter_entry_context' ), PHP_INT_MIN );
	}

	/**
	 * Give the blocks of an entry the context of its event.
	 *
	 * Hooked to render_block_context only while an entry renders.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $context Block context.
	 *
	 * @return array<string, mixed> Context with this event's postId and postType.
	 */
	public function filter_entry_context( array $context ): array {
		$context['postType'] = $this->current_post_type;
		$context['postId']   = $this->current_post_id;

		if ( empty( $this->current_span_data ) ) {
			return $context;
		}

		$context['gatherpress/eventSpan']      = $this->current_span_data['span'] ?? 'single';
		$context['gatherpress/eventDayIndex']  = $this->current_span_data['day_index'] ?? 1;
		$context['gatherpress/eventTotalDays'] = $this->current_span_data['total_days'] ?? 1;
		$context['gatherpress/isAllDay']       = ! empty( $this->current_span_data['is_all_day'] );

		if ( isset( $this->current_span_data['slot'] ) ) {
			$context['gatherpress/eventSlot'] = $this->current_span_data['slot'];
		}

		return $context;
	}

	/**
	 * Get the screen reader text that says which day of a multi-day event an entry shows.
	 *
	 * @since TBD
	 *
	 * @return string For example ", day 2 of 5", or an empty string for a one-day event.
	 */
	private function get_span_text(): string {
		$total_days = $this->get_int( $this->current_span_data, 'total_days', 1 );

		if ( $total_days <= 1 ) {
			return '';
		}

		return sprintf(
			/* translators: 1: Day of the event, 2: Number of days of the event. Read by screen readers. */
			__( ', day %1$d of %2$d', 'gatherpress' ),
			$this->get_int( $this->current_span_data, 'day_index', 1 ),
			$total_days
		);
	}

	/**
	 * Add screen reader text to an element before its first closing tag of a type.
	 *
	 * @since TBD
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param string $text          Screen reader text.
	 * @param string $closing_tag   Closing tag before which the text goes, for example '</a>'.
	 *
	 * @return string Block HTML with the text, or unchanged when the tag is missing.
	 */
	private function insert_screen_reader_text(
		string $block_content,
		string $text,
		string $closing_tag
	): string {
		$position = stripos( $block_content, $closing_tag );

		if ( false === $position || '' === $text ) {
			return $block_content;
		}

		return substr_replace(
			$block_content,
			sprintf( '<span class="%s">%s</span>', self::SCREEN_READER_CLASS, esc_html( $text ) ),
			$position,
			0
		);
	}

	/**
	 * Add the day of a multi-day event to the Post Title of an entry.
	 *
	 * Each day of a multi-day event shows the same title, so screen readers get the day, for
	 * example "Retreat, day 2 of 5". The text goes inside the link when the title has one, so
	 * it is part of the name of the link or modal trigger.
	 *
	 * Hooked to render_block_core/post-title only while an entry renders. A Post Title inside
	 * the modal does not change, because the modal shows the full dates.
	 *
	 * @since TBD
	 *
	 * @param string $block_content Rendered core/post-title block HTML.
	 *
	 * @return string Block HTML with the day as screen reader text.
	 */
	public function filter_post_title_span( string $block_content ): string {
		$text = $this->modal_depth > 0 ? '' : $this->get_span_text();

		if ( false !== stripos( $block_content, '</a>' ) ) {
			return $this->insert_screen_reader_text( $block_content, $text, '</a>' );
		}

		$last_close = strrpos( $block_content, '</' );

		if ( false === $last_close || '' === $text ) {
			return $block_content;
		}

		return substr_replace(
			$block_content,
			sprintf( '<span class="%s">%s</span>', self::SCREEN_READER_CLASS, esc_html( $text ) ),
			$last_close,
			0
		);
	}

	/**
	 * Add the event title to the Event Date link of an entry.
	 *
	 * Hooked to render_block_gatherpress/event-date only while an entry
	 * renders. It acts only when the template shows no Post Title.
	 *
	 * @since TBD
	 *
	 * @param string              $block_content Rendered gatherpress/event-date block HTML.
	 * @param array<mixed, mixed> $parsed_block  Parsed gatherpress/event-date block.
	 *
	 * @return string Block HTML with the hidden title in the link.
	 */
	public function filter_event_date_link( string $block_content, array $parsed_block ): string {
		if ( $this->current_shows_post_title ) {
			return $block_content;
		}

		return $this->add_title_to_event_link( $block_content, $parsed_block, $this->current_post_id );
	}

	/**
	 * Give an untitled event a placeholder title.
	 *
	 * The core/post-title block renders nothing for an empty title. When the
	 * title is the modal trigger, an untitled event would have no link to
	 * open it. Hooked to the_title only while an entry renders, and only the
	 * title of that entry's event changes.
	 *
	 * Other code can call the_title with any types, so the arguments are
	 * checked, not typed.
	 *
	 * @since TBD
	 *
	 * @param mixed $title Title from the_title.
	 * @param mixed $id    Post ID from the_title.
	 *
	 * @return mixed The title, or the placeholder for this event's empty title.
	 */
	public function filter_empty_title( $title, $id = 0 ) {
		$is_this_post = is_numeric( $id ) && (int) $id === $this->current_post_id;
		$is_empty     = ! is_string( $title ) || '' === trim( wp_strip_all_tags( $title ) );

		return $is_this_post && $is_empty ? __( '(no title)', 'gatherpress' ) : $title;
	}

	/**
	 * Add the event title to an Event Date link as screen reader text.
	 *
	 * In a calendar cell the Event Date link shows only the time, so screen
	 * readers cannot tell the events of one day apart. In the default template
	 * the link is also the modal trigger, and GatherPress gives it
	 * role="button", so this names a link or a button (WCAG 4.1.2, and 2.4.4
	 * for templates without the modal). This puts a visually hidden title
	 * inside the link, the same way core/read-more does. Where the time is
	 * visible, it stays first in the name (WCAG 2.5.3); the Dots style hides
	 * the time, so there the title is the only useful part of the name. On a
	 * multi-day event, the day of the event follows the title, except inside
	 * the modal, which shows the full dates.
	 *
	 * The title comes from the same post the Event Date block shows, so a
	 * block with its own `postId` attribute gets that event's title, not
	 * the title of the calendar entry.
	 *
	 * Only links get the title. A link or button needs a name that says where
	 * it goes (WCAG 2.4.4, 4.1.2). A date without a link is plain text: it
	 * shows the same time to everyone, so a hidden title would give screen
	 * reader users content that the visible calendar does not show.
	 *
	 * The Event Date block limits its output to a single `<a href>` element,
	 * so the first `</a>` closes that link. Without a link, or without a
	 * title, the content is returned unchanged.
	 *
	 * @since TBD
	 *
	 * @param string              $block_content   Rendered gatherpress/event-date block HTML.
	 * @param array<mixed, mixed> $parsed_block    Parsed gatherpress/event-date block.
	 * @param int                 $default_post_id ID of the event this calendar entry renders.
	 *
	 * @return string Block HTML with the hidden title in the link.
	 */
	private function add_title_to_event_link(
		string $block_content,
		array $parsed_block,
		int $default_post_id
	): string {
		// Mirror Blocks\Setup::get_post_id(): a postId attribute wins.
		$attrs   = is_array( $parsed_block['attrs'] ?? null ) ? $parsed_block['attrs'] : array();
		$post_id = is_numeric( $attrs['postId'] ?? null ) ? (int) $attrs['postId'] : 0;
		$post    = get_post( $post_id > 0 ? $post_id : $default_post_id );

		if ( ! $post instanceof WP_Post ) {
			return $block_content;
		}

		$post_title = the_title_attribute(
			array(
				'post' => $post,
				'echo' => false,
			)
		);

		if ( ! is_string( $post_title ) || '' === trim( $post_title ) ) {
			return $block_content;
		}

		$screen_reader_text = sprintf(
			/* translators: %s: Event title. Read by screen readers after the event time. */
			__( ', %s', 'gatherpress' ),
			$post_title
		);

		// The modal shows the full dates, so it needs no day of the event, as in filter_post_title_span().
		$span_text = $this->modal_depth > 0 ? '' : $this->get_span_text();

		return $this->insert_screen_reader_text( $block_content, $screen_reader_text . $span_text, '</a>' );
	}

	/**
	 * Check whether an entry template shows a Post Title outside the modal.
	 *
	 * When it does, the title is already on screen and read by screen
	 * readers, so a hidden title in the Event Date link would be read twice.
	 * A Post Title inside gatherpress/modal does not count: the modal stays
	 * hidden until the trigger opens it.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $blocks Parsed blocks of the entry template.
	 *
	 * @return bool True if a core/post-title block is outside every modal.
	 */
	private function template_shows_post_title( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = $block['blockName'] ?? '';

			if ( 'core/post-title' === $block_name ) {
				return true;
			}

			$inner_blocks = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();

			if ( 'gatherpress/modal' !== $block_name && $this->template_shows_post_title( $inner_blocks ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve a preset or inline color value from attributes or styles.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes  Block attributes.
	 * @param array<mixed>         $style_color Inline color style array.
	 * @param string               $named_attr  Named attribute key (e.g. 'textColor').
	 * @param string               $style_prop  Style property key (e.g. 'text').
	 * @param string               $preset_type Preset type ('color' or 'gradient').
	 *
	 * @return string Resolved CSS value or empty string.
	 */
	private function resolve_color_value(
		array $attributes,
		array $style_color,
		string $named_attr,
		string $style_prop,
		string $preset_type
	): string {
		$named_val = is_string( $attributes[ $named_attr ] ?? null ) ? $attributes[ $named_attr ] : '';

		if ( '' !== $named_val ) {
			return "var:preset|{$preset_type}|{$named_val}";
		}

		return is_string( $style_color[ $style_prop ] ?? null ) ? $style_color[ $style_prop ] : '';
	}

	/**
	 * Get a nested array from the style attribute, for example style.color.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               ...$keys    Keys below style, outermost first.
	 *
	 * @return array<mixed> The nested array, or an empty array.
	 */
	private function get_style_array( array $attributes, string ...$keys ): array {
		$value = $attributes['style'] ?? null;

		foreach ( $keys as $key ) {
			$value = is_array( $value ) ? ( $value[ $key ] ?? null ) : null;
		}

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Extract color styles from attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array<string, string>
	 */
	private function extract_color_styles( array $attributes ): array {
		$color_styles = array();
		$style_color  = $this->get_style_array( $attributes, 'color' );
		$mapping      = array(
			'text'       => array( 'textColor', 'text', 'color' ),
			'background' => array( 'backgroundColor', 'background', 'color' ),
			'gradient'   => array( 'gradient', 'gradient', 'gradient' ),
		);

		foreach ( $mapping as $prop => $config ) {
			$value = $this->resolve_color_value( $attributes, $style_color, $config[0], $config[1], $config[2] );

			if ( '' !== $value ) {
				$color_styles[ $prop ] = $value;
			}
		}

		return $color_styles;
	}

	/**
	 * Extract border styles from attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_border_styles( array $attributes ): array {
		$border_styles = array();
		$border_color  = is_string( $attributes['borderColor'] ?? null ) ? $attributes['borderColor'] : '';

		if ( '' !== $border_color ) {
			$border_styles['color'] = "var:preset|color|{$border_color}";
		}

		foreach ( $this->get_style_array( $attributes, 'border' ) as $key => $val ) {
			if ( is_string( $key ) ) {
				$border_styles[ $key ] = $val;
			}
		}

		return $border_styles;
	}

	/**
	 * Extract shadow style from attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return string|null Resolved shadow style or null.
	 */
	private function extract_shadow_style( array $attributes ): ?string {
		$style  = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$shadow = $style['shadow'] ?? $attributes['shadow'] ?? null;

		if ( ! is_string( $shadow ) || '' === $shadow ) {
			return null;
		}

		if ( str_starts_with( $shadow, 'var:preset|' ) || str_contains( $shadow, ' ' ) ) {
			return $shadow;
		}

		return "var:preset|shadow|{$shadow}";
	}

	/**
	 * Extract spacing styles from attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array<string, mixed> Spacing styles.
	 */
	private function extract_spacing_styles( array $attributes ): array {
		$spacing = $this->get_style_array( $attributes, 'spacing' );

		return array_filter(
			array(
				'padding' => $spacing['padding'] ?? null,
				'margin'  => $spacing['margin'] ?? null,
			)
		);
	}

	/**
	 * Builds CSS styles and classnames for an entry using wp_style_engine_get_styles.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array{classnames: string, inline_styles: string} Resolved CSS class names and inline style values.
	 */
	private function get_entry_styles_and_classes( array $attributes ): array {
		$link_color    = $this->get_style_array( $attributes, 'elements', 'link', 'color' );
		$extra_classes = array( 'gatherpress-calendar__entry' );

		if ( ! empty( $link_color['text'] ) ) {
			$extra_classes[] = 'has-link-color';
		}

		$block_styles = array_filter(
			array(
				'color'   => $this->extract_color_styles( $attributes ),
				'border'  => $this->extract_border_styles( $attributes ),
				'shadow'  => $this->extract_shadow_style( $attributes ),
				'spacing' => $this->extract_spacing_styles( $attributes ),
			)
		);

		/**
		 * The style engine returns an empty array when there are no styles, so each key is optional.
		 *
		 * @var array{css?: string, declarations?: array<string, string>, classnames?: string} $styles
		 */
		$styles     = wp_style_engine_get_styles( $block_styles, array( 'convert_vars_to_classnames' => false ) );
		$classnames = array_merge( $extra_classes, explode( ' ', $styles['classnames'] ?? '' ) );

		$inline_styles = $styles['css'] ?? '';
		$text_color    = $styles['declarations']['color'] ?? '';

		// The dot of the "Colored Dots" style has no visible text, so it uses the text color as its background.
		if ( '' !== $text_color ) {
			$dot_color = safecss_filter_attr( '--gatherpress-calendar-dot-color:' . $text_color );

			if ( '' !== $dot_color ) {
				$inline_styles .= $dot_color . ';';
			}
		}

		return array(
			'classnames'    => trim( implode( ' ', array_filter( $classnames ) ) ),
			'inline_styles' => $inline_styles,
		);
	}

	/**
	 * Renders a set of parsed blocks (this block's own template) with
	 * whatever `render_block_context` filters are currently active - used
	 * to render the calendar-entries template once per event, with that event's
	 * postId/postType injected via the filter set up by the caller.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<string, mixed>> $parsed_blocks Parsed inner blocks (the template).
	 *
	 * @return string Rendered HTML.
	 */
	private function render_template( array $parsed_blocks ): string {
		if ( empty( $parsed_blocks ) ) {
			return '';
		}

		// Wrap the template blocks as the inner blocks of a no-op parent so
		// that WP_Block::render()'s own inner-blocks loop applies the
		// `render_block_context` filter to each of them.
		$wrapper_block = array(
			'blockName'    => 'core/null',
			'attrs'        => array(),
			'innerBlocks'  => $parsed_blocks,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $parsed_blocks ), null ),
		);

		return ( new WP_Block( $wrapper_block ) )->render( array( 'dynamic' => false ) );
	}
}
