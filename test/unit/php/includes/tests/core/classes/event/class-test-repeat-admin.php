<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Repeat_Admin.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Repeat_Admin;
use GatherPress\Core\Event\Rules;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Repeat_Admin.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Repeat_Admin
 */
class Test_Repeat_Admin extends Base {

	/**
	 * Reset the cached counts and the screen.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		Utility::set_and_get_hidden_property( Repeat_Admin::get_instance(), 'counts', array() );
	}

	/**
	 * Restore the front end.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tear_down(): void {
		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * An event repeating every Monday from 2099.
	 *
	 * @since TBD
	 *
	 * @param string $status The post status.
	 *
	 * @return int The post ID.
	 */
	protected function repeating( string $status = 'draft' ): int {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		( new Event( $post_id ) )->rules()->replace(
			array(
				array(
					'starts_on' => '2099-01-05',
					'rrule'     => 'FREQ=WEEKLY;BYDAY=MO',
					'times'     => array( '19:00' ),
					'timezone'  => 'UTC',
					'duration'  => 60,
				),
			)
		);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		return $post_id;
	}

	/**
	 * The class's hooks.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Repeat_Admin::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'filter',
					'name'     => 'query_vars',
					'priority' => 10,
					'callback' => array( $instance, 'add_query_var' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'pre_get_posts',
					'priority' => 10,
					'callback' => array( $instance, 'filter_list' ),
				),
				array(
					'type'     => 'filter',
					'name'     => 'display_post_states',
					'priority' => 10,
					'callback' => array( $instance, 'add_state' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'registered_post_type',
					'priority' => 10,
					'callback' => array( $instance, 'register_post_type_hooks' ),
				),
			),
			$instance
		);
	}

	/**
	 * The view and column hooks go on event post types only, after the list's own.
	 *
	 * @since TBD
	 *
	 * @covers ::register_post_type_hooks
	 * @covers ::add_query_var
	 *
	 * @return void
	 */
	public function test_register_post_type_hooks(): void {
		$instance = Repeat_Admin::get_instance();

		$instance->register_post_type_hooks( 'post' );
		$this->assertFalse( has_filter( 'views_edit-post', array( $instance, 'add_view' ) ) );

		$instance->register_post_type_hooks( Event::POST_TYPE );
		$this->assertSame( 11, has_filter( 'views_edit-' . Event::POST_TYPE, array( $instance, 'add_view' ) ) );
		$this->assertSame(
			11,
			has_action( 'manage_' . Event::POST_TYPE . '_posts_custom_column', array( $instance, 'add_summary' ) )
		);
		$this->assertContains( Repeat_Admin::QUERY_VAR, $instance->add_query_var( array() ) );
	}

	/**
	 * The Repeating view lists only repeating events.
	 *
	 * @since TBD
	 *
	 * @covers ::filter_list
	 *
	 * @return void
	 */
	public function test_filter_list(): void {
		$instance = Repeat_Admin::get_instance();

		// The visit runs the query on the front end, where the view doesn't apply.
		$this->go_to( add_query_arg( Repeat_Admin::QUERY_VAR, '1', get_post_type_archive_link( Event::POST_TYPE ) ) );
		$query = $GLOBALS['wp_the_query'];

		$this->assertSame( '', $query->get( 'meta_query' ), 'Not outside the admin.' );

		set_current_screen( 'edit-' . Event::POST_TYPE );
		$instance->filter_list( $query );

		$this->assertSame(
			array(
				array(
					'key'     => Rules::META_KEY,
					'compare' => 'EXISTS',
				),
			),
			$query->get( 'meta_query' )
		);

		$this->go_to( get_post_type_archive_link( Event::POST_TYPE ) );
		set_current_screen( 'edit-' . Event::POST_TYPE );
		$instance->filter_list( $GLOBALS['wp_the_query'] );

		$this->assertSame( '', $GLOBALS['wp_the_query']->get( 'meta_query' ), 'Not without the view.' );
	}

	/**
	 * The Repeating view follows Past, counts repeating events, and is the only current view when chosen.
	 *
	 * @since TBD
	 *
	 * @covers ::add_view
	 * @covers ::count
	 *
	 * @return void
	 */
	public function test_add_view(): void {
		$instance = Repeat_Admin::get_instance();
		$views    = array(
			'all'      => '<a href="#" class="current" aria-current="page">All</a>',
			'upcoming' => '<a href="#">Upcoming</a>',
			'past'     => '<a href="#">Past</a>',
			'publish'  => '<a href="#">Published</a>',
		);

		$this->assertSame( $views, $instance->add_view( $views ), 'Without a screen.' );

		$this->repeating();
		$this->repeating( 'publish' );
		$this->repeating( 'trash' );
		$this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		set_current_screen( 'edit-' . Event::POST_TYPE );

		$result = $instance->add_view( $views );

		$this->assertSame( array( 'all', 'upcoming', 'past', 'repeating', 'publish' ), array_keys( $result ) );
		$this->assertStringContainsString( 'Repeating <span class="count">(2)</span>', $result['repeating'] );
		$this->assertStringContainsString( Repeat_Admin::QUERY_VAR . '=1', $result['repeating'] );
		$this->assertStringContainsString( 'class="current"', $result['all'] );

		$this->set_url_params( array( Repeat_Admin::QUERY_VAR => '1' ) );
		$result = $instance->add_view( array( 'all' => $views['all'] ) );

		$this->assertSame( array( 'all', 'repeating' ), array_keys( $result ), 'Last without Past.' );
		$this->assertStringNotContainsString( 'current', $result['all'] );
		$this->assertStringContainsString( 'class="current" aria-current="page"', $result['repeating'] );
	}

	/**
	 * A repeating event is marked after its title.
	 *
	 * @since TBD
	 *
	 * @covers ::add_state
	 * @covers ::repeats
	 *
	 * @return void
	 */
	public function test_add_state(): void {
		$instance = Repeat_Admin::get_instance();
		$one_off  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$page     = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$states   = $instance->add_state( array(), get_post( $this->repeating() ) );

		$this->assertStringContainsString( 'dashicons-controls-repeat', $states['gatherpress_repeating'] );
		$this->assertStringEndsWith( 'Repeating', $states['gatherpress_repeating'] );
		$this->assertSame( array(), $instance->add_state( array(), get_post( $one_off ) ) );
		$this->assertSame( array(), $instance->add_state( array(), get_post( $page ) ) );
	}

	/**
	 * The date column says how a repeating event repeats.
	 *
	 * @since TBD
	 *
	 * @covers ::add_summary
	 *
	 * @return void
	 */
	public function test_add_summary(): void {
		$instance = Repeat_Admin::get_instance();
		$post_id  = $this->repeating();
		$one_off  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		ob_start();
		$instance->add_summary( 'datetime', $post_id );
		$instance->add_summary( 'rsvps', $post_id );
		$instance->add_summary( 'datetime', $one_off );
		$output = ob_get_clean();

		$this->assertSame( '<br><span class="gatherpress-repeat-summary">Every week on Monday.</span>', $output );
	}
}
