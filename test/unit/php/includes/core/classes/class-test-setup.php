<?php
/**
 * Class handles unit tests for GatherPress\Core\Setup.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Setup;
use GatherPress\Core\Venue;
use PMC\Unit_Test\Base;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Setup
 */
class Test_Setup extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'delete_event' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_flush_gatherpress_rewrite_rules' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_categories_all',
				'priority' => 10,
				'callback' => array( $instance, 'register_gatherpress_block_category' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'wpmu_drop_tables',
				'priority' => 10,
				'callback' => array( $instance, 'on_site_delete' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'set_custom_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'sortable_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'get_the_date',
				'priority' => 10,
				'callback' => array( $instance, 'get_the_event_date' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_time',
				'priority' => 10,
				'callback' => array( $instance, 'get_the_event_date' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'body_class',
				'priority' => 10,
				'callback' => array( $instance, 'add_gatherpress_body_classes' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'display_post_states',
				'priority' => 10,
				'callback' => array( $instance, 'set_event_archive_labels' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'plugin_action_links_%s/%s', basename( GATHERPRESS_CORE_PATH ), basename( GATHERPRESS_CORE_FILE ) ),
				'priority' => 10,
				'callback' => array( $instance, 'filter_plugin_action_links' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'network_admin_plugin_action_links_%s/%s', basename( GATHERPRESS_CORE_PATH ), basename( GATHERPRESS_CORE_FILE ) ),
				'priority' => 10,
				'callback' => array( $instance, 'filter_plugin_action_links' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for check_users_can_register method.
	 *
	 * @covers ::check_users_can_register
	 *
	 * @return void
	 */
	public function test_check_users_can_register(): void {
		$instance                   = Setup::get_instance();
		$users_can_register_name    = 'users_can_register';
		$users_can_register_default = get_option( $users_can_register_name );
		update_option( $users_can_register_name, 1 );
		$instance->check_users_can_register();
		$this->assertEquals(
			get_option( $users_can_register_name ),
			1,
			'Failed to assert user registration option was set.'
		);
	}

	/**
	 * Coverage for filter_plugin_action_links method.
	 *
	 * @covers ::filter_plugin_action_links
	 *
	 * @return void
	 */
	public function test_filter_plugin_action_links(): void {
		$instance = Setup::get_instance();

		$actions = array(
			'unit-test' => '<a href="https://unit.test">Unit Test</a>',
		);

		$response = $instance->filter_plugin_action_links( $actions );

		$this->assertSame(
			'<a href="https://unit.test">Unit Test</a>',
			$response['unit-test'],
			'Failed to assert unit-test link matches.'
		);
		$this->assertSame(
			'<a href="' . esc_url( admin_url( 'edit.php?post_type=gp_event&page=gp_general' ) ) . '">Settings</a>',
			$response['settings'],
			'Failed to assert settings link matches.'
		);
	}

	/**
	 * Coverage for add_gatherpress_body_classes method.
	 *
	 * @covers ::add_gatherpress_body_classes
	 *
	 * @return void
	 */
	public function test_add_gatherpress_body_classes(): void {
		$instance = Setup::get_instance();
		$classes  = array( 'unit-test' );
		$expects  = array(
			'unit-test',
			'gp-enabled',
			sprintf( 'gp-theme-%s', esc_attr( get_stylesheet() ) ),
		);

		$this->assertSame(
			$expects,
			$instance->add_gatherpress_body_classes( $classes ),
			'Failed to assert the array of body classes matches.'
		);
	}

	/**
	 * Coverage for register_gatherpress_block_category method.
	 *
	 * @covers ::register_gatherpress_block_category
	 *
	 * @return void
	 */
	public function test_register_gatherpress_block_category(): void {
		$instance = Setup::get_instance();
		$default  = array(
			array(
				'slug'  => 'unit-test',
				'title' => 'Unit Test',
				'icon'  => 'unittest',
			),
		);
		$expects  = array(
			array(
				'slug'  => 'gatherpress',
				'title' => 'GatherPress',
				'icon'  => 'nametag',
			),
			array(
				'slug'  => 'unit-test',
				'title' => 'Unit Test',
				'icon'  => 'unittest',
			),
		);

		$this->assertSame(
			$expects,
			$instance->register_gatherpress_block_category( $default ),
			'Failed to assert correct block categories.'
		);
	}

	/**
	 * Coverage for add_online_event_term method.
	 *
	 * @covers ::add_online_event_term
	 *
	 * @return void
	 */
	public function test_add_online_event_term(): void {
		$instance = Setup::get_instance();
		$slug     = 'online-event';

		$this->assertEmpty(
			term_exists( $slug, Venue::TAXONOMY ),
			'Failed to assert online-event term does not exist.'
		);

		$instance->add_online_event_term();

		$term_ids = term_exists( $slug, Venue::TAXONOMY );
		$term     = get_term_by( 'term_id', $term_ids['term_id'], Venue::TAXONOMY );

		$this->assertSame( $slug, $term->slug, 'Failed to assert that term slugs match.' );
		$this->assertSame( 'Online event', $term->name, 'Failed to assert that term names match.' );

		add_filter(
			'gettext',
			static function( $translation ): string {
				if ( 'Online event' === $translation ) {
					return 'Online';
				}

				return $translation;
			}
		);

		$instance->add_online_event_term();

		$term = get_term_by( 'term_id', $term->term_id, Venue::TAXONOMY );

		$this->assertSame( $slug, $term->slug, 'Failed to assert that term slugs match.' );
		$this->assertSame( 'Online', $term->name, 'Failed to assert that term names match.' );
	}

	/**
	 * Coverage for sortable_columns method.
	 *
	 * @covers ::sortable_columns
	 *
	 * @return void
	 */
	public function test_sortable_columns(): void {
		$instance = Setup::get_instance();
		$default  = array( 'unit' => 'test' );
		$expects  = array(
			'unit'     => 'test',
			'datetime' => 'datetime',
		);

		$this->assertSame(
			$expects,
			$instance->sortable_columns( $default ),
			'Failed to assert correct sortable columns.'
		);
	}
}
