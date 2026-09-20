<?php
/**
 * Unit tests for the uninstall settings page.
 *
 * @package GatherPress\Core\Settings
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Event;
use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Uninstall;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Uninstall.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Uninstall
 */
class Test_Uninstall extends Base {

	/**
	 * The options the page declares.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<string, mixed>> The options, keyed by settings key.
	 */
	protected function declared_options(): array {
		$sections = (array) Utility::invoke_hidden_method( Uninstall::get_instance(), 'get_sections' );

		return (array) ( $sections['uninstall']['options'] ?? array() );
	}

	/**
	 * The page identifies itself where the settings API expects it to.
	 *
	 * @covers ::get_slug
	 * @covers ::get_name
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_page_identity(): void {
		$instance = Uninstall::get_instance();

		$this->assertSame(
			'uninstall_settings',
			Utility::invoke_hidden_method( $instance, 'get_slug' ),
			'The slug is what the settings page is addressed by.'
		);
		$this->assertSame(
			'Uninstall',
			Utility::invoke_hidden_method( $instance, 'get_name' ),
			'The name is the tab label.'
		);
		$this->assertSame(
			PHP_INT_MAX - 1,
			Utility::invoke_hidden_method( $instance, 'get_priority' ),
			'The page sits after Tools and before Credits.'
		);
	}

	/**
	 * Every gated task gets a checkbox that defaults to off.
	 *
	 * @covers ::get_sections
	 * @covers ::get_task_options
	 *
	 * @return void
	 */
	public function test_every_task_has_a_checkbox_defaulting_to_off(): void {
		$options = $this->declared_options();

		foreach ( Preferences::task_keys() as $task ) {
			$key = Preferences::option_key( $task );

			$this->assertArrayHasKey(
				$key,
				$options,
				sprintf( 'Task "%s" must be visible on the screen that governs it.', $task )
			);
			$this->assertSame(
				'checkbox',
				$options[ $key ]['field']['type'],
				sprintf( 'Task "%s" is a yes or no question.', $task )
			);
			$this->assertFalse(
				$options[ $key ]['field']['options']['default'],
				sprintf( 'Task "%s" must be off until somebody turns it on.', $task )
			);
		}
	}

	/**
	 * None of the choices travel in a settings file.
	 *
	 * @covers ::get_task_options
	 *
	 * @return void
	 */
	public function test_no_choice_is_exportable(): void {
		foreach ( $this->declared_options() as $key => $option ) {
			$this->assertFalse(
				$option['exportable'],
				sprintf( '%s must not be carried between sites by a settings file.', $key )
			);
		}
	}

	/**
	 * The settings API is told to refuse these keys on import.
	 *
	 * @covers ::get_task_options
	 *
	 * @return void
	 */
	public function test_choices_are_refused_by_an_import(): void {
		$settings = Settings::get_instance();
		$blocked  = $settings->get_non_importable_keys();
		$absent   = $settings->get_non_exportable_keys();

		foreach ( Preferences::task_keys() as $task ) {
			$key = Preferences::option_key( $task );

			$this->assertContains( $key, $absent, sprintf( '%s is left out of an export.', $key ) );
			$this->assertContains( $key, $blocked, sprintf( '%s is refused by an import.', $key ) );
		}
	}

	/**
	 * The RSVP choice is only offered while events are staying.
	 *
	 * @covers ::get_task_options
	 *
	 * @return void
	 */
	public function test_rsvp_choice_is_hidden_once_events_are_going(): void {
		$options = $this->declared_options();
		$rsvps   = $options[ Preferences::option_key( Preferences::TASK_RSVPS ) ];

		$this->assertSame(
			array( Preferences::option_key( Preferences::TASK_EVENTS ) => '' ),
			$rsvps['show_if'],
			'Removing events removes the RSVPs on them, so the choice is not offered twice.'
		);
	}

	/**
	 * No other choice is conditional.
	 *
	 * @covers ::get_task_options
	 *
	 * @return void
	 */
	public function test_every_other_choice_stands_on_its_own(): void {
		foreach ( $this->declared_options() as $key => $option ) {
			if ( Preferences::option_key( Preferences::TASK_RSVPS ) === $key ) {
				continue;
			}

			$this->assertArrayNotHasKey(
				'show_if',
				$option,
				sprintf( '%s is a choice of its own.', $key )
			);
		}
	}

	/**
	 * The copy above the choices says what deleting costs.
	 *
	 * @covers ::get_section_description
	 *
	 * @return void
	 */
	public function test_section_description_warns_before_it_explains(): void {
		$description = (string) Utility::invoke_hidden_method(
			Uninstall::get_instance(),
			'get_section_description'
		);

		$this->assertStringContainsString(
			'<strong>These choices cannot be undone.</strong>',
			$description,
			'The warning is the part somebody has to read.'
		);
		$this->assertStringContainsString(
			'<code>wp plugin uninstall gatherpress --deactivate</code>',
			$description,
			'A large site is told how to delete without hitting a request timeout.'
		);
	}

	/**
	 * The registered post type label reaches every part of a row.
	 *
	 * @covers ::get_task_options
	 *
	 * @return void
	 */
	public function test_registered_label_reaches_every_part_of_the_row(): void {
		$object   = get_post_type_object( Event::POST_TYPE );
		$original = $object->labels->name;

		$object->labels->name = 'Gatherings';

		try {
			$events = $this->declared_options()[ Preferences::option_key( Preferences::TASK_EVENTS ) ];
		} finally {
			$object->labels->name = $original;
		}

		$this->assertSame( 'Gatherings', $events['labels']['name'], 'The row heading reads the registered label.' );
		$this->assertSame( 'Remove Gatherings', $events['field']['label'], 'The checkbox reads it too.' );
		$this->assertStringContainsString(
			'Removes all Gatherings',
			$events['description'],
			'So does the description, or aria-describedby would contradict the control.'
		);
	}
}
