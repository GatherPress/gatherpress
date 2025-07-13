<?php
/**
 * Class handles unit tests for GatherPress\Core\Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Assets;
use GatherPress\Core\Setup;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

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
				'callback' => array( $instance, 'maybe_flush_rewrite_rules' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_notices',
				'priority' => 10,
				'callback' => array( $instance, 'check_gatherpress_alpha' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'network_admin_notices',
				'priority' => 10,
				'callback' => array( $instance, 'check_gatherpress_alpha' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_initialize_site',
				'priority' => 10,
				'callback' => array( $instance, 'on_site_create' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'send_headers',
				'priority' => 10,
				'callback' => array( $instance, 'smash_table' ),
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
				'name'     => 'body_class',
				'priority' => 10,
				'callback' => array( $instance, 'add_gatherpress_body_classes' ),
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
			'<a href="' . esc_url( admin_url( 'edit.php?post_type=gatherpress_event&page=gatherpress_general' ) ) . '">Settings</a>',
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
			'gatherpress-enabled',
			sprintf( 'gatherpress-theme-%s', esc_attr( get_stylesheet() ) ),
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
			static function ( $translation ): string {
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
	 * Coverage for maybe_create_flush_rewrite_rules_flag method.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_create_flush_rewrite_rules_flag(): void {
		$instance = Setup::get_instance();
		$this->assertFalse(
			get_option( 'gatherpress_flush_rewrite_rules_flag' ),
			'Failed to assert that flush rewrite rules option does not exist.'
		);

		Utility::invoke_hidden_method( $instance, 'maybe_create_flush_rewrite_rules_flag' );

		$this->assertTrue(
			get_option( 'gatherpress_flush_rewrite_rules_flag' ),
			'Failed to assert that flush rewrite rules option exists.'
		);

		// Cleanup.
		delete_option( 'gatherpress_flush_rewrite_rules_flag' );
	}
}
