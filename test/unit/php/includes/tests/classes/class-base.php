<?php
/**
 * Base test class for GatherPress.
 *
 * @package GatherPress
 * @since   1.0.0
 */

namespace GatherPress\Tests;

use PMC\Unit_Test\Base as PMC_Base;
use ReflectionException;
use ReflectionMethod;

/**
 * Define as abstract class to prevent test suite from scanning for test methods.
 */
abstract class Base extends PMC_Base {
	/**
	 * Asserts hooks are registered correctly and counts them.
	 *
	 * This method extends the parent assert_hooks functionality by also verifying
	 * that the number of action and filter hooks found in the setup_hooks method
	 * matches what is expected based on the hooks array.
	 *
	 * @since 1.0.0
	 *
	 * @param array      $hooks                Array of hooks to assert with structure:
	 *                                         [
	 *                                             'type'     => (string) 'action' or 'filter',
	 *                                             'name'     => (string) Hook name,
	 *                                             'priority' => (int) Priority,
	 *                                             'callback' => (callable) Callback,
	 *                                         ].
	 * @param object     $class_instance_closure Instance of the class being tested.
	 * @param array|null $maybe_invoke_methods   Optional. Methods to invoke.
	 *
	 * @return void
	 */
	public function assert_hooks(
		array $hooks,
		$class_instance_closure = null,
		array $maybe_invoke_methods = array()
	): void {
		parent::assert_hooks( $hooks, $class_instance_closure, $maybe_invoke_methods );

		// Get class name from instance.
		$class_name = get_class( $class_instance_closure );

		// Count actions and filters from hooks array.
		$expected_counts = array(
			'actions' => count(
				array_filter(
					$hooks,
					function ( $hook ) {
						return 'action' === $hook['type'];
					}
				)
			),
			'filters' => count(
				array_filter(
					$hooks,
					function ( $hook ) {
						return 'filter' === $hook['type'];
					}
				)
			),
		);

		// Get actual counts from setup_hooks method.
		$actual_counts = $this->count_hook_registrations(
			get_class( $class_instance_closure ),
			'setup_hooks'
		);

		$this->assertEquals(
			$expected_counts['actions'],
			$actual_counts['actions'],
			sprintf(
				'Expected %d actions but found %d in setup_hooks()',
				$expected_counts['actions'],
				$actual_counts['actions']
			)
		);

		$this->assertEquals(
			$expected_counts['filters'],
			$actual_counts['filters'],
			sprintf(
				'Expected %d filters but found %d in setup_hooks()',
				$expected_counts['filters'],
				$actual_counts['filters']
			)
		);
	}

	/**
	 * Count the number of hook registrations in a method.
	 *
	 * Analyzes the method's code to count add_filter and add_action calls.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class_name  The name of the class containing the method.
	 * @param string $method_name The name of the method to analyze.
	 *
	 * @return array{actions: int, filters: int} Array with counts of actions and filters.
	 */
	public function count_hook_registrations(
		string $class_name,
		string $method_name
	): array {
		try {
			$reflection = new ReflectionMethod( $class_name, $method_name );
		} catch ( ReflectionException $e ) {
			return array(
				'actions' => 0,
				'filters' => 0,
			);
		}

		$start_line = $reflection->getStartLine();
		$end_line   = $reflection->getEndLine();
		$file       = $reflection->getFileName();

		if ( false === $file ) {
			return array(
				'actions' => 0,
				'filters' => 0,
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$source = file_get_contents( $file );

		if ( false === $source ) {
			return array(
				'actions' => 0,
				'filters' => 0,
			);
		}

		$tokens = token_get_all( $source );

		$action_count = 0;
		$filter_count = 0;
		$current_line = 1;
		$in_method    = false;

		foreach ( $tokens as $token ) {
			// Skip non-array tokens.
			if ( ! is_array( $token ) ) {
				continue;
			}

			list( $token_id, $token_text, $token_line ) = $token;

			// Track current line number.
			$current_line = $token_line;

			// Check if we're in the target method.
			if ( $current_line >= $start_line && $current_line <= $end_line ) {
				$in_method = true;
			} elseif ( $current_line > $end_line ) {
				break;
			}

			if ( $in_method && T_STRING === $token_id ) {
				if ( 'add_action' === $token_text ) {
					++$action_count;
				} elseif ( 'add_filter' === $token_text ) {
					++$filter_count;
				}
			}
		}

		return array(
			'actions' => $action_count,
			'filters' => $filter_count,
		);
	}

	/**
	 * Mock a function's return value for testing.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $function_name The name of the function to mock.
	 * @param callable $callback      The callback to use as the mock.
	 *
	 * @return void
	 */
	public function set_fn_return( string $function_name, callable $callback ): void {
		// Store the mock callback in a global variable for the namespaced function to use.
		$GLOBALS[ 'gatherpress_test_' . $function_name . '_mock' ] = $callback;
	}

	/**
	 * Remove function mock.
	 *
	 * @since 1.0.0
	 *
	 * @param string $function_name The name of the function to unmock.
	 *
	 * @return void
	 */
	public function unset_fn_return( string $function_name ): void {
		unset( $GLOBALS[ 'gatherpress_test_' . $function_name . '_mock' ] );
	}
}
