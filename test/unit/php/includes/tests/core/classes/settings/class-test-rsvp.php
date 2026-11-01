<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Rsvp.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Rsvp;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Rsvp
 */
class Test_Rsvp extends Base {

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Rsvp::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'rsvp_settings', $slug, 'Failed to assert slug is rsvp_settings.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Rsvp::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'RSVP', $name, 'Failed to assert name is RSVP.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Rsvp::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( 2, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = Rsvp::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_sections' );
		$this->assertSame(
			'RSVP Defaults',
			$section['rsvp_defaults']['name'],
			'Failed to assert name is RSVP Defaults.'
		);
		$this->assertIsArray(
			$section['rsvp_cleanup'],
			'Failed to assert rsvp_cleanup is an array.'
		);
	}

	/**
	 * The reminder timing checkboxes follow the filtered timings.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called from inside get_sections().
	 *
	 * @since TBD
	 *
	 * @covers ::get_reminder_timing_options
	 *
	 * @return void
	 */
	public function test_get_reminder_timing_options(): void {
		$instance = Rsvp::get_instance();
		$options  = Utility::invoke_hidden_method( $instance, 'get_reminder_timing_options' );

		$this->assertSame(
			array( 'event_reminder_day_before', 'event_reminder_hour_before' ),
			array_keys( $options )
		);
		$this->assertSame( 'Reminder Timing', $options['event_reminder_day_before']['labels']['name'] );
		$this->assertSame(
			'',
			$options['event_reminder_hour_before']['labels']['name'],
			'Only the first row is headed.'
		);
		$this->assertSame( '', $options['event_reminder_day_before']['description'] );
		$this->assertSame( '', $options['event_reminder_hour_before']['description'] );
		$this->assertSame( 'checkbox', $options['event_reminder_day_before']['field']['type'] );
		$this->assertTrue( $options['event_reminder_day_before']['field']['options']['default'] );
		$this->assertFalse( $options['event_reminder_hour_before']['field']['options']['default'] );
		$this->assertSame( '1', $options['event_reminder_hour_before']['show_if']['enable_event_reminders'] );

		$filter = static function ( array $timings ): array {
			unset( $timings['hour_before'] );

			$timings['week_before'] = array(
				'label'   => 'A week before',
				'seconds' => WEEK_IN_SECONDS,
				'default' => false,
			);

			return $timings;
		};

		add_filter( 'gatherpress_event_reminder_timings', $filter );
		$options = Utility::invoke_hidden_method( $instance, 'get_reminder_timing_options' );
		remove_filter( 'gatherpress_event_reminder_timings', $filter );

		$this->assertSame(
			array( 'event_reminder_day_before', 'event_reminder_week_before' ),
			array_keys( $options )
		);
		$this->assertSame( 'A week before', $options['event_reminder_week_before']['field']['label'] );
	}

	/**
	 * The mode-dependent fields hide when RSVP Mode is disabled, and the
	 * cleanup schedule fields hide when cleanup is off — both via the
	 * `show_if` negation form.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections_show_if_dependencies(): void {
		$sections = Utility::invoke_hidden_method( Rsvp::get_instance(), 'get_sections' );

		$mode_dependent = array(
			'enable_open_rsvp',
			'capacity',
			'guest_limit',
			'enable_anonymous_rsvp',
		);
		foreach ( $mode_dependent as $option ) {
			$this->assertSame(
				array( 'rsvp_mode' => array( 'not' => 'disabled' ) ),
				$sections['rsvp_defaults']['options'][ $option ]['show_if'],
				sprintf( '%s should hide when RSVP Mode is disabled.', $option )
			);
		}

		foreach ( array( 'rsvp_cleanup_frequency', 'rsvp_cleanup_multiplier' ) as $option ) {
			$this->assertSame(
				array( 'enable_rsvp_cleanup' => array( 'not' => 'disabled' ) ),
				$sections['rsvp_cleanup']['options'][ $option ]['show_if'],
				sprintf( '%s should hide when cleanup is off.', $option )
			);
		}
	}
}
