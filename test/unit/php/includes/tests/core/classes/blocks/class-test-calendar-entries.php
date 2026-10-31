<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar_Entries.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Calendar_Entries;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;

/**
 * Class Test_Calendar_Entries.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar_Entries
 */
class Test_Calendar_Entries extends Base {

	/**
	 * Span data of day 2 of a 3-day event.
	 *
	 * @var array<string, mixed>
	 */
	const MIDDLE_DAY = array(
		'span'       => 'middle',
		'day_index'  => 2,
		'total_days' => 3,
		'slot'       => 1,
		'row_span'   => 2,
	);

	/**
	 * Clear the render state of the entries class.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$instance = Calendar_Entries::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'current_post_id', 0 );
		Utility::set_and_get_hidden_property( $instance, 'current_span_data', array() );
		Utility::set_and_get_hidden_property( $instance, 'current_shows_post_title', false );
		Utility::set_and_get_hidden_property( $instance, 'modal_depth', 0 );
		Utility::set_and_get_hidden_property( $instance, 'render_depth', 0 );
		parent::tearDown();
	}

	/**
	 * The constructor hooks nothing: render.php calls render(), and the entry filters are added only
	 * while an entry renders.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_registers_no_hooks(): void {
		$instance = Calendar_Entries::get_instance();

		Utility::invoke_hidden_method( $instance, '__construct' );

		$this->assertFalse( has_filter( 'the_title', array( $instance, 'filter_empty_title' ) ) );
	}

	/**
	 * Create an event.
	 *
	 * @param string $title Event title.
	 *
	 * @return int Event post ID.
	 */
	private function create_event( string $title = 'Calendar Entries Test Event' ): int {
		$post = $this->mock->post(
			array(
				'post_title' => $title,
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'datetime_start' => '2026-10-14 10:00:00',
				'datetime_end'   => '2026-10-16 12:00:00',
				'timezone'       => 'UTC',
			)
		);

		return $post->ID;
	}

	/**
	 * Render an entries block with the given template and day posts.
	 *
	 * @param string               $template  Block markup of the entry template.
	 * @param array<mixed>         $day_posts Day posts, as the week block passes them.
	 * @param array<string, mixed> $attrs     Entries block attributes.
	 * @param array<string, mixed> $context   More block context.
	 *
	 * @return string Rendered HTML.
	 */
	private function render_entries(
		string $template,
		array $day_posts,
		array $attrs = array(),
		array $context = array()
	): string {
		$markup = sprintf(
			'<!-- wp:gatherpress/calendar-entries %s -->%s<!-- /wp:gatherpress/calendar-entries -->',
			empty( $attrs ) ? '' : wp_json_encode( $attrs ),
			$template
		);

		$block          = new WP_Block( parse_blocks( $markup )[0] );
		$block->context = array_merge( array( 'gatherpress/dayPosts' => $day_posts ), $context );

		return Calendar_Entries::get_instance()->render( $block->attributes, $block );
	}

	/**
	 * Entries without posts render nothing, except in a time grid.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_without_posts(): void {
		$this->assertSame( '', $this->render_entries( '', array() ) );

		$output = $this->render_entries(
			'',
			array(),
			array(),
			array( 'gatherpress/timeGridConfig' => new Time_Grid( true ) )
		);

		$this->assertStringContainsString( 'gatherpress--has-time-grid', $output );
		$this->assertStringEndsWith( '></div>', $output, 'Failed to assert an empty time grid column.' );
	}

	/**
	 * Each post of the day renders the template once, with its span data.
	 *
	 * @covers ::render
	 * @covers ::render_entry
	 * @covers ::render_entry_inner_content
	 * @covers ::render_entry_template
	 * @covers ::render_template
	 * @covers ::get_block_attributes
	 * @covers ::get_int
	 * @covers ::build_span_classes
	 * @covers ::build_entry_data_attributes
	 * @covers ::build_entry_style_attribute
	 * @covers ::build_timed_event_style
	 * @covers ::build_entry_iapi_directives
	 * @covers ::filter_entry_context
	 *
	 * @return void
	 */
	public function test_render_entries(): void {
		$post_id  = $this->mock->post( array( 'post_title' => 'Plain Calendar Post' ) )->get()->ID;
		$event_id = $this->create_event();
		$output   = $this->render_entries(
			'<!-- wp:post-title /-->',
			array(
				$post_id,
				array_merge(
					self::MIDDLE_DAY,
					array(
						'id'           => $event_id,
						'is_continued' => true,
						'is_continues' => true,
					)
				),
				array( 'id' => PHP_INT_MAX ),
				'not an entry',
			),
			array( 'style' => array( 'spacing' => array( 'blockGap' => '6px' ) ) )
		);

		$this->assertStringContainsString( 'style="gap: 6px"', $output );
		$this->assertSame(
			2,
			substr_count( $output, 'gatherpress-calendar__entry' ),
			'Failed to assert missing posts are skipped.'
		);
		$this->assertStringContainsString( 'Plain Calendar Post', $output );
		$this->assertStringContainsString( 'Calendar Entries Test Event', $output );
		$this->assertStringContainsString( 'gatherpress--is-span-middle', $output );
		$this->assertStringContainsString( 'gatherpress--is-multi-day', $output );
		$this->assertStringContainsString( 'gatherpress--is-span-continued', $output );
		$this->assertStringContainsString( 'gatherpress--is-span-continues', $output );
		$this->assertStringContainsString( 'gatherpress--has-multi-col-span', $output );
		$this->assertStringContainsString(
			'data-span="middle" data-day-index="2" data-total-days="3" data-slot="1" data-row-span="2"',
			$output
		);
		$this->assertStringContainsString(
			'style="--gatherpress-calendar-entry-slot: 1; --gatherpress-calendar-row-span: 2;"',
			$output
		);
		$this->assertStringContainsString( 'data-wp-interactive="gatherpress/calendar"', $output );
		$this->assertStringContainsString( 'data-wp-context="{&quot;eventId&quot;:' . $event_id . '}"', $output );
		$this->assertStringContainsString(
			'data-row-span="1"',
			$output,
			'Failed to assert a plain post spans one column.'
		);
	}

	/**
	 * Timed and all-day entries get their classes and placement.
	 *
	 * @covers ::render_entry
	 * @covers ::build_span_classes
	 * @covers ::build_timed_event_style
	 *
	 * @return void
	 */
	public function test_render_timed_and_all_day_entries(): void {
		$event_id = $this->create_event();
		$output   = $this->render_entries(
			'<!-- wp:post-title /-->',
			array(
				array(
					'id'             => $event_id,
					'span'           => 'single',
					'is_timed'       => true,
					'time_row_start' => 9,
					'time_row_end'   => 13,
					'overlap_total'  => 2,
					'overlap_index'  => 1,
				),
			)
		);

		$this->assertStringContainsString( 'gatherpress--is-timed-event', $output );
		$this->assertStringContainsString(
			'--gatherpress-event-time-row-start: 9; --gatherpress-event-time-row-end: 13; '
				. '--gatherpress-overlap-total: 2; --gatherpress-overlap-index: 1;',
			$output
		);

		$output = $this->render_entries(
			'<!-- wp:post-title /-->',
			array(
				array(
					'id'         => $event_id,
					'is_all_day' => true,
					'is_timed'   => true,
				),
			)
		);

		$this->assertStringContainsString( 'gatherpress--is-all-day', $output );
		$this->assertStringContainsString( 'gatherpress--is-multi-day', $output, 'Failed to assert all-day events are ribbons.' );
		$this->assertStringNotContainsString(
			'gatherpress--is-timed-event',
			$output,
			'Failed to assert a timed entry needs its rows.'
		);
	}

	/**
	 * The color, border, shadow and spacing attributes style each entry.
	 *
	 * @covers ::get_entry_styles_and_classes
	 * @covers ::extract_color_styles
	 * @covers ::resolve_color_value
	 * @covers ::extract_border_styles
	 * @covers ::extract_shadow_style
	 * @covers ::extract_spacing_styles
	 * @covers ::get_style_array
	 *
	 * @return void
	 */
	public function test_entry_styles(): void {
		$post_id = $this->mock->post()->get()->ID;
		$output  = $this->render_entries(
			'<!-- wp:post-title /-->',
			array( $post_id ),
			array(
				'textColor'       => 'vivid-red',
				'backgroundColor' => 'pale-pink',
				'borderColor'     => 'black',
				'style'           => array(
					'border'   => array( 'width' => '2px' ),
					'shadow'   => 'natural',
					'spacing'  => array( 'padding' => array( 'top' => '4px' ) ),
					'elements' => array( 'link' => array( 'color' => array( 'text' => '#000000' ) ) ),
				),
			)
		);

		$this->assertStringContainsString( 'has-link-color', $output );
		$this->assertStringContainsString( 'var(--wp--preset--color--vivid-red)', $output );
		$this->assertStringContainsString( 'var(--wp--preset--color--pale-pink)', $output );
		$this->assertStringContainsString( 'has-black-border-color', $output );
		$this->assertStringContainsString( 'border-width:2px', $output );
		$this->assertStringContainsString( 'var(--wp--preset--shadow--natural)', $output );
		$this->assertStringContainsString( 'padding-top:4px', $output );
		$this->assertStringContainsString(
			'--gatherpress-calendar-dot-color:var(--wp--preset--color--vivid-red);',
			$output,
			'Failed to assert the dot color follows the text color.'
		);

		$output = $this->render_entries(
			'<!-- wp:post-title /-->',
			array( $post_id ),
			array(
				'gradient' => 'cool-to-warm',
				'shadow'   => '1px 1px #333333',
				'style'    => array( 'color' => array( 'text' => '#123456' ) ),
			)
		);

		$this->assertStringContainsString( 'color:#123456', $output );
		$this->assertStringContainsString( 'var(--wp--preset--gradient--cool-to-warm)', $output );
		$this->assertStringContainsString( '1px 1px #333333', $output, 'Failed to assert a shadow value is kept.' );

		$output = $this->render_entries(
			'<!-- wp:post-title /-->',
			array( $post_id ),
			array( 'style' => array( 'shadow' => 'var:preset|shadow|deep' ) )
		);

		$this->assertStringContainsString( 'var(--wp--preset--shadow--deep)', $output );
		$this->assertStringNotContainsString( '--gatherpress-calendar-dot-color', $output );
	}

	/**
	 * A multi-day entry tells screen readers its day in the Post Title, but not inside the modal.
	 *
	 * @covers ::filter_post_title_span
	 * @covers ::get_span_text
	 * @covers ::insert_screen_reader_text
	 * @covers ::track_modal_depth_pre
	 * @covers ::track_modal_depth_post
	 * @covers ::is_modal_block
	 *
	 * @return void
	 */
	public function test_post_title_span_text(): void {
		$event_id = $this->create_event( 'Retreat' );
		$entry    = array_merge( self::MIDDLE_DAY, array( 'id' => $event_id ) );
		$modal    = '<!-- wp:gatherpress/modal --><div class="wp-block-gatherpress-modal">'
			. '<!-- wp:gatherpress/modal-content --><div class="wp-block-gatherpress-modal-content">'
			. '<!-- wp:post-title /--></div><!-- /wp:gatherpress/modal-content --></div><!-- /wp:gatherpress/modal -->';

		$output = $this->render_entries( '<!-- wp:post-title {"isLink":true} /-->' . $modal, array( $entry ) );

		$this->assertStringContainsString(
			'<span class="gatherpress--screen-reader-text">, day 2 of 3</span></a>',
			$output,
			'Failed to assert the day is part of the link name.'
		);
		$this->assertSame(
			1,
			substr_count( $output, ', day 2 of 3' ),
			'Failed to assert the modal title does not change.'
		);

		$output = $this->render_entries( '<!-- wp:post-title /-->', array( $entry ) );

		$this->assertMatchesRegularExpression(
			'#Retreat<span class="gatherpress--screen-reader-text">, day 2 of 3</span></h[1-6]>#',
			$output,
			'Failed to assert the day goes inside a title without a link.'
		);

		$output = $this->render_entries( '<!-- wp:post-title {"isLink":true} /-->', array( $event_id ) );

		$this->assertStringNotContainsString( ', day ', $output, 'Failed to assert a one-day entry has no day text.' );
	}

	/**
	 * A template without a Post Title keeps the Event Date link of a multi-day event, as in 0.5.0.
	 *
	 * @covers ::render_entry_template
	 * @covers ::template_shows_post_title
	 * @covers ::filter_event_date_link
	 * @covers ::add_title_to_event_link
	 *
	 * @return void
	 */
	public function test_event_date_link_is_kept_without_post_title(): void {
		$event_id = $this->create_event( 'Retreat' );
		$template = '<!-- wp:gatherpress/event-date {"isLink":true} /-->'
			. '<!-- wp:gatherpress/modal --><div class="wp-block-gatherpress-modal">'
			. '<!-- wp:post-title /--></div><!-- /wp:gatherpress/modal -->';
		$output   = $this->render_entries(
			$template,
			array( array_merge( self::MIDDLE_DAY, array( 'id' => $event_id ) ) )
		);

		$this->assertStringContainsString(
			'wp-block-gatherpress-event-date',
			$output,
			'Failed to assert the trigger stays.'
		);
		$this->assertStringContainsString(
			'<span class="gatherpress--screen-reader-text">, Retreat, day 2 of 3</span></a>',
			$output,
			'Failed to assert the hidden title and day in the Event Date link.'
		);
	}

	/**
	 * With a Post Title as the trigger, a multi-day ribbon hides its Event Date outside the modal.
	 *
	 * @covers ::render_entry_template
	 * @covers ::template_shows_post_title
	 * @covers ::filter_multiday_event_date
	 * @covers ::is_current_entry_multi_day
	 *
	 * @return void
	 */
	public function test_multi_day_event_date_hides_outside_modal(): void {
		$event_id = $this->create_event( 'Retreat' );
		$template = '<!-- wp:post-title {"isLink":true} /--><!-- wp:gatherpress/event-date /-->';

		$output = $this->render_entries(
			$template,
			array( array_merge( self::MIDDLE_DAY, array( 'id' => $event_id ) ) )
		);
		$this->assertStringNotContainsString( 'wp-block-gatherpress-event-date', $output );

		$output = $this->render_entries( $template, array( $event_id ) );
		$this->assertStringContainsString(
			'wp-block-gatherpress-event-date',
			$output,
			'Failed to assert a one-day event keeps its date.'
		);

		$modal  = '<!-- wp:post-title /--><!-- wp:gatherpress/modal --><div class="wp-block-gatherpress-modal">'
			. '<!-- wp:gatherpress/event-date /--></div><!-- /wp:gatherpress/modal -->';
		$output = $this->render_entries( $modal, array( array_merge( self::MIDDLE_DAY, array( 'id' => $event_id ) ) ) );

		$this->assertStringContainsString(
			'wp-block-gatherpress-event-date',
			$output,
			'Failed to assert the modal keeps the date.'
		);

		$nested = '<!-- wp:group --><div class="wp-block-group"><!-- wp:post-title /--></div><!-- /wp:group -->'
			. '<!-- wp:gatherpress/event-date /-->';
		$output = $this->render_entries(
			$nested,
			array( array_merge( self::MIDDLE_DAY, array( 'id' => $event_id ) ) )
		);

		$this->assertStringNotContainsString(
			'wp-block-gatherpress-event-date',
			$output,
			'Failed to assert a Post Title in a Group is also a trigger.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				Calendar_Entries::get_instance(),
				'template_shows_post_title',
				array( array( 'not a block', array( 'blockName' => 'core/post-title' ) ) )
			)
		);
	}

	/**
	 * A calendar inside an entry template leaves the filters and the modal depth of the outer entry.
	 *
	 * The outer entry has a nested calendar and an Event Date in its modal, and a Post Title after
	 * the modal. Both come after the nested calendar has rendered its own entries.
	 *
	 * @covers ::render_entry_template
	 * @covers ::add_entry_filters
	 * @covers ::remove_entry_filters
	 *
	 * @return void
	 */
	public function test_nested_calendar_keeps_outer_entry_state(): void {
		$instance = Calendar_Entries::get_instance();

		$event_id = $this->create_event( 'Retreat' );
		$template = '<!-- wp:gatherpress/modal --><div class="wp-block-gatherpress-modal">'
			. '<!-- wp:gatherpress/modal-content --><div class="wp-block-gatherpress-modal-content">'
			. Test_Calendar::get_calendar_markup()
			. '<!-- wp:gatherpress/event-date /-->'
			. '</div><!-- /wp:gatherpress/modal-content --></div><!-- /wp:gatherpress/modal -->'
			. '<!-- wp:post-title /-->';
		$entry    = array(
			'id'         => $event_id,
			'span'       => 'middle',
			'day_index'  => 7,
			'total_days' => 9,
		);
		$output   = $this->render_entries( $template, array( $entry ) );

		$this->assertStringContainsString(
			', day 2 of 3',
			$output,
			'Failed to assert the nested calendar renders its own entries.'
		);
		$this->assertMatchesRegularExpression(
			'#Retreat<span class="gatherpress--screen-reader-text">, day 7 of 9</span></h[1-6]>#',
			$output,
			'Failed to assert the outer Post Title keeps its filter after the nested calendar.'
		);
		$this->assertStringContainsString(
			'wp-block-gatherpress-event-date',
			$output,
			'Failed to assert the outer modal keeps its date after the nested calendar.'
		);
		$this->assertSame( 0, Utility::get_hidden_property( $instance, 'render_depth' ) );
		$this->assertSame( 0, Utility::get_hidden_property( $instance, 'modal_depth' ) );
		$this->assertFalse(
			has_filter( 'the_title', array( $instance, 'filter_empty_title' ) ),
			'Failed to assert the filters are removed after the outer entry.'
		);
	}

	/**
	 * A callback that throws while an entry renders leaves no filters and no entry state behind.
	 *
	 * @covers ::render_entry_template
	 *
	 * @return void
	 */
	public function test_throwing_callback_restores_state(): void {
		$instance = Calendar_Entries::get_instance();

		$event_id = $this->create_event( 'Retreat' );
		$callback = static function () {
			throw new \RuntimeException( 'Calendar entry test' );
		};

		add_action( 'gatherpress_calendar_entry_render', $callback );

		try {
			$this->render_entries( '<!-- wp:post-title /-->', array( $event_id ) );
			$this->fail( 'Failed to assert the exception reaches the caller.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'Calendar entry test', $exception->getMessage() );
		} finally {
			remove_action( 'gatherpress_calendar_entry_render', $callback );
		}

		$this->assertSame( 0, Utility::get_hidden_property( $instance, 'render_depth' ) );
		$this->assertSame( 0, Utility::get_hidden_property( $instance, 'current_post_id' ) );
		$this->assertFalse(
			has_filter( 'the_title', array( $instance, 'filter_empty_title' ) ),
			'Failed to assert the filters are removed.'
		);
	}

	/**
	 * In the modal, the Event Date link gets the hidden title but not the day, as the Post Title does.
	 *
	 * @covers ::add_title_to_event_link
	 *
	 * @return void
	 */
	public function test_modal_event_date_link_has_no_span_text(): void {
		$event_id = $this->create_event( 'Retreat' );
		$template = '<!-- wp:gatherpress/event-date {"isLink":true} /-->'
			. '<!-- wp:gatherpress/modal --><div class="wp-block-gatherpress-modal">'
			. '<!-- wp:gatherpress/modal-content --><div class="wp-block-gatherpress-modal-content">'
			. '<!-- wp:gatherpress/event-date {"isLink":true} /-->'
			. '</div><!-- /wp:gatherpress/modal-content --></div><!-- /wp:gatherpress/modal -->';
		$output   = $this->render_entries(
			$template,
			array( array_merge( self::MIDDLE_DAY, array( 'id' => $event_id ) ) )
		);

		$this->assertSame(
			1,
			substr_count( $output, ', Retreat, day 2 of 3</span></a>' ),
			'Failed to assert only the trigger link gets the day.'
		);
		$this->assertSame(
			1,
			substr_count( $output, ', Retreat</span></a>' ),
			'Failed to assert the modal link gets the title without the day.'
		);
	}

	/**
	 * Entries read their day posts and template safely.
	 *
	 * @covers ::render
	 * @covers ::render_entry_inner_content
	 * @covers ::get_entry_styles_and_classes
	 * @covers ::extract_shadow_style
	 *
	 * @return void
	 */
	public function test_render_with_missing_context_and_template(): void {
		$instance = Calendar_Entries::get_instance();

		$block          = new WP_Block( array( 'blockName' => Calendar_Entries::BLOCK_NAME ) );
		$block->context = array( 'gatherpress/timeGridConfig' => new Time_Grid( true ) );

		$this->assertStringContainsString( 'gatherpress--has-time-grid', $instance->render( array(), $block ) );

		$block->context = array( 'gatherpress/dayPosts' => array( $this->mock->post()->get()->ID ) );
		$output         = $instance->render( array(), $block );

		$this->assertStringContainsString(
			'gatherpress-calendar__entry',
			$output,
			'Failed to assert an entry without a template.'
		);
		$this->assertStringNotContainsString( 'box-shadow', $output );
	}

	/**
	 * An untitled post gets a placeholder title, and the global post is restored.
	 *
	 * @covers ::filter_empty_title
	 * @covers ::render_entry_inner_content
	 *
	 * @return void
	 */
	public function test_empty_title_and_global_post(): void {
		$instance = Calendar_Entries::get_instance();

		$untitled        = $this->mock->post( array( 'post_title' => '' ) )->get();
		$page_post       = $this->mock->post( array( 'post_title' => 'The Page' ) )->get();
		$GLOBALS['post'] = $page_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$output = $this->render_entries( '<!-- wp:post-title {"isLink":true} /-->', array( $untitled->ID ) );

		$this->assertStringContainsString( '(no title)', $output );
		$this->assertSame( $page_post, $GLOBALS['post'], 'Failed to assert the page post is restored.' );

		unset( $GLOBALS['post'] );

		Utility::set_and_get_hidden_property( $instance, 'current_post_id', $untitled->ID );

		$this->assertSame( 'Other', $instance->filter_empty_title( 'Other', $page_post->ID ) );
		$this->assertSame( 'Kept', $instance->filter_empty_title( 'Kept', $untitled->ID ) );
		$this->assertSame( '(no title)', $instance->filter_empty_title( null, $untitled->ID ) );
	}

	/**
	 * The calendar fires an action before each entry renders.
	 *
	 * @covers ::render_entry_template
	 *
	 * @return void
	 */
	public function test_entry_render_action(): void {
		$post_id  = $this->mock->post()->get()->ID;
		$received = array();
		$callback = static function ( ...$args ) use ( &$received ) {
			$received = $args;
		};

		add_action( 'gatherpress_calendar_entry_render', $callback, 10, 4 );
		$this->render_entries( '<!-- wp:post-title /-->', array( array( 'id' => $post_id ) ) );
		remove_action( 'gatherpress_calendar_entry_render', $callback, 10 );

		$this->assertSame( $post_id, $received[0] );
		$this->assertSame( 'post', $received[1] );
		$this->assertSame( 'core/post-title', $received[2][0]['blockName'] );
		$this->assertSame( array( 'id' => $post_id ), $received[3] );
	}

	/**
	 * An entry without a template renders an empty entry.
	 *
	 * @covers ::render_template
	 *
	 * @return void
	 */
	public function test_render_without_template(): void {
		$post_id = $this->mock->post()->get()->ID;
		$output  = $this->render_entries( '', array( $post_id ) );

		$this->assertStringContainsString( 'gatherpress-calendar__entry', $output );
		$this->assertMatchesRegularExpression( '#data-wp-on--focusout="actions.unfocusEvent"[^>]*></div>#', $output );
	}

	/**
	 * The filters leave other content alone.
	 *
	 * @covers ::filter_event_date_link
	 * @covers ::add_title_to_event_link
	 * @covers ::filter_post_title_span
	 * @covers ::filter_multiday_event_date
	 * @covers ::track_modal_depth_pre
	 * @covers ::track_modal_depth_post
	 * @covers ::filter_entry_context
	 *
	 * @return void
	 */
	public function test_filters_leave_other_content(): void {
		$instance = Calendar_Entries::get_instance();

		$titled   = $this->mock->post( array( 'post_title' => 'Titled' ) )->get()->ID;
		$untitled = $this->mock->post( array( 'post_title' => '' ) )->get()->ID;

		Utility::set_and_get_hidden_property( $instance, 'current_post_id', $untitled );

		$this->assertSame( '10:00', $instance->filter_event_date_link( '10:00', array() ) );
		$this->assertSame( '<a>10:00</a>', $instance->filter_event_date_link( '<a>10:00</a>', array() ) );
		$this->assertSame(
			'<a>10:00<span class="gatherpress--screen-reader-text">, Titled</span></a>',
			$instance->filter_event_date_link(
				'<a>10:00</a>',
				array( 'attrs' => array( 'postId' => $titled ) )
			),
			'Failed to assert a postId attribute names its own event.'
		);

		Utility::set_and_get_hidden_property( $instance, 'current_post_id', PHP_INT_MAX );
		$this->assertSame( '<a>10:00</a>', $instance->filter_event_date_link( '<a>10:00</a>', array() ) );

		Utility::set_and_get_hidden_property( $instance, 'current_span_data', self::MIDDLE_DAY );
		$this->assertSame( 'Plain title', $instance->filter_post_title_span( 'Plain title' ) );
		$this->assertSame(
			'<div>date</div>',
			$instance->filter_multiday_event_date( '<div>date</div>' ),
			'Failed to assert the date stays when the template shows no Post Title.'
		);

		Utility::set_and_get_hidden_property( $instance, 'current_shows_post_title', true );
		$this->assertSame( '', $instance->filter_multiday_event_date( '<div>date</div>' ) );
		$this->assertSame(
			'<a>10:00</a>',
			$instance->filter_event_date_link(
				'<a>10:00</a>',
				array( 'attrs' => array( 'postId' => $titled ) )
			),
			'Failed to assert no hidden title when the template shows the Post Title.'
		);

		$instance->track_modal_depth_pre( null, array( 'blockName' => 'gatherpress/modal' ) );
		$this->assertSame( '<div>date</div>', $instance->filter_multiday_event_date( '<div>date</div>' ) );
		$this->assertSame(
			'x',
			$instance->track_modal_depth_post( 'x', array( 'blockName' => 'core/paragraph' ) )
		);
		$this->assertSame( 1, Utility::get_hidden_property( $instance, 'modal_depth' ) );
		$instance->track_modal_depth_post( '', array( 'blockName' => 'gatherpress/modal' ) );
		$instance->track_modal_depth_post( '', array( 'blockName' => 'gatherpress/modal' ) );
		$this->assertSame( 0, Utility::get_hidden_property( $instance, 'modal_depth' ) );

		$context = $instance->filter_entry_context( array() );
		$this->assertSame( 1, $context['gatherpress/eventSlot'] );
		$this->assertSame( 'middle', $context['gatherpress/eventSpan'] );

		Utility::set_and_get_hidden_property( $instance, 'current_span_data', array() );
		$this->assertSame(
			array(
				'postType' => '',
				'postId'   => PHP_INT_MAX,
			),
			$instance->filter_entry_context( array() )
		);
	}
}
