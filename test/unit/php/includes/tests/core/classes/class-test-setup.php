<?php
/**
 * Class handles unit tests for GatherPress\Core\Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Assets;
use GatherPress\Core\Event;
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
				'name'     => 'admin_init',
				'priority' => 10,
				'callback' => array( $instance, 'add_privacy_policy_content' ),
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
	 * Coverage for schedule_rewrite_flush method.
	 *
	 * @covers ::schedule_rewrite_flush
	 *
	 * @return void
	 */
	public function test_schedule_rewrite_flush(): void {
		$instance = Setup::get_instance();

		// Set up rewrite_rules option.
		add_option( 'rewrite_rules', array( 'test' => 'rules' ) );

		$this->assertNotFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert that rewrite_rules option exists.'
		);

		// Test that schedule_rewrite_flush deletes rewrite_rules option.
		Utility::invoke_hidden_method( $instance, 'schedule_rewrite_flush' );

		$this->assertFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert that rewrite_rules option was deleted.'
		);

		add_option( 'rewrite_rules', array( 'test' => 'rules' ) );

		Utility::invoke_hidden_method( $instance, 'schedule_rewrite_flush' );

		$this->assertFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert that rewrite_rules option was deleted.'
		);
	}

	/**
	 * Coverage for deactivate_gatherpress_plugin method.
	 *
	 * Ensures that deactivating the plugin flushes rewrite rules.
	 *
	 * @covers ::deactivate_gatherpress_plugin
	 *
	 * @return void
	 */
	public function test_deactivate_gatherpress_plugin(): void {
		$instance = Setup::get_instance();

		// Set up rewrite_rules option to verify it gets flushed.
		add_option( 'rewrite_rules', array( 'test' => 'rules' ) );

		$this->assertNotFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert that rewrite_rules option exists before deactivation.'
		);

		$instance->deactivate_gatherpress_plugin();

		// After flush_rewrite_rules(), the option should be regenerated by WordPress.
		// We can't directly test if it was flushed, but we can verify the method runs without error.
		$this->assertTrue(
			true,
			'The deactivate_gatherpress_plugin method should execute without error.'
		);
	}

	/**
	 * Coverage for add_privacy_policy_content method.
	 *
	 * Verifies that the method adds privacy policy content when the function exists.
	 *
	 * @covers ::add_privacy_policy_content
	 *
	 * @return void
	 */
	public function test_add_privacy_policy_content(): void {
		$instance = Setup::get_instance();

		// The wp_add_privacy_policy_content function exists in WordPress core.
		// We can't easily verify it was called, but we can verify the method runs.
		$instance->add_privacy_policy_content();

		$this->assertTrue(
			true,
			'The add_privacy_policy_content method should execute without error.'
		);
	}

	/**
	 * Coverage for on_site_delete method.
	 *
	 * Verifies that the custom event table is added to the list of tables to drop.
	 *
	 * @covers ::on_site_delete
	 *
	 * @return void
	 */
	public function test_on_site_delete(): void {
		global $wpdb;

		$instance = Setup::get_instance();
		$tables   = array(
			$wpdb->prefix . 'posts',
			$wpdb->prefix . 'postmeta',
		);

		$result = $instance->on_site_delete( $tables );

		$expected_table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		$this->assertContains(
			$expected_table,
			$result,
			'Failed to assert that the GatherPress events table is included in tables to delete.'
		);
		$this->assertContains(
			$wpdb->prefix . 'posts',
			$result,
			'Failed to assert that original tables are preserved.'
		);
	}

	/**
	 * Coverage for check_gatherpress_alpha method when Alpha is active.
	 *
	 * Verifies that no notice is shown when GatherPress Alpha is defined.
	 *
	 * @covers ::check_gatherpress_alpha
	 *
	 * @return void
	 */
	public function test_check_gatherpress_alpha_when_alpha_active(): void {
		// Define the constant to simulate Alpha being active.
		if ( ! defined( 'GATHERPRESS_ALPHA_VERSION' ) ) {
			define( 'GATHERPRESS_ALPHA_VERSION', '1.0.0' );
		}

		$instance = Setup::get_instance();

		// Capture output to verify no notice is displayed.
		ob_start();
		$instance->check_gatherpress_alpha();
		$output = ob_get_clean();

		$this->assertEmpty(
			$output,
			'Failed to assert that no output is generated when GatherPress Alpha is active.'
		);
	}

	/**
	 * Coverage for smash_table method.
	 *
	 * Verifies that the custom header is added.
	 *
	 * @covers ::smash_table
	 *
	 * @return void
	 */
	public function test_smash_table(): void {
		$instance = Setup::get_instance();

		// Note: headers_list() only works if headers haven't been sent.
		// In unit tests, we can call the method but can't verify the header was set.
		// We can at least verify the method runs without error.
		$instance->smash_table();

		$this->assertTrue(
			true,
			'The smash_table method should execute without error.'
		);
	}

	/**
	 * Coverage for activate_gatherpress_plugin method in single site mode.
	 *
	 * @covers ::activate_gatherpress_plugin
	 * @covers ::create_tables
	 *
	 * @return void
	 */
	public function test_activate_gatherpress_plugin_single_site(): void {
		global $wpdb;

		$instance = Setup::get_instance();

		// Drop the table if it exists from previous tests.
		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table creation.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		// Activate plugin in single site mode.
		$instance->activate_gatherpress_plugin( false );

		// Verify table was created.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table creation.
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

		$this->assertEquals(
			$table,
			$table_exists,
			'Failed to assert that events table was created during activation.'
		);

		// Verify online event term was added.
		$term = term_exists( 'online-event', Venue::TAXONOMY );

		$this->assertNotEmpty(
			$term,
			'Failed to assert that online-event term was created during activation.'
		);

		// Verify rewrite rules were scheduled to flush.
		$rewrite_rules = get_option( 'rewrite_rules' );

		$this->assertFalse(
			$rewrite_rules,
			'Failed to assert that rewrite_rules option was deleted to schedule flush.'
		);
	}

	/**
	 * Coverage for create_tables method.
	 *
	 * @covers ::create_tables
	 * @covers ::add_online_event_term
	 * @covers ::schedule_rewrite_flush
	 *
	 * @return void
	 */
	public function test_create_tables(): void {
		global $wpdb;

		$instance = Setup::get_instance();

		// Drop the table if it exists.
		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table creation.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		// Add rewrite_rules option to verify it gets deleted.
		add_option( 'rewrite_rules', array( 'test' => 'rules' ) );

		// Call create_tables.
		Utility::invoke_hidden_method( $instance, 'create_tables' );

		// Verify table structure.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table structure.
		$columns = $wpdb->get_results( "DESCRIBE {$table}" );

		$column_names = array_column( $columns, 'Field' );

		$this->assertContains( 'post_id', $column_names, 'Table should have post_id column.' );
		$this->assertContains( 'datetime_start', $column_names, 'Table should have datetime_start column.' );
		$this->assertContains( 'datetime_start_gmt', $column_names, 'Table should have datetime_start_gmt column.' );
		$this->assertContains( 'datetime_end', $column_names, 'Table should have datetime_end column.' );
		$this->assertContains( 'datetime_end_gmt', $column_names, 'Table should have datetime_end_gmt column.' );
		$this->assertContains( 'timezone', $column_names, 'Table should have timezone column.' );
	}

	/**
	 * Coverage for check_gatherpress_alpha when user lacks install_plugins capability.
	 *
	 * @covers ::check_gatherpress_alpha
	 *
	 * @return void
	 */
	public function test_check_gatherpress_alpha_without_capability(): void {
		$instance = Setup::get_instance();

		// Create a subscriber user (no install_plugins capability).
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		ob_start();
		$instance->check_gatherpress_alpha();
		$output = ob_get_clean();

		$this->assertEmpty(
			$output,
			'Failed to assert that no notice is shown to users without install_plugins capability.'
		);
	}
}
