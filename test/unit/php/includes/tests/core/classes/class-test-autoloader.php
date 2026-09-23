<?php
/**
 * Class handles unit tests for GatherPress\Core\Autoloader.
 *
 * @package GatherPress\Tests\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use Closure;
use GatherPress\Core\Autoloader;
use GatherPress\Tests\Base;
use ReflectionFunction;

/**
 * Class Test_Autoloader.
 *
 * @coversDefaultClass \GatherPress\Core\Autoloader
 */
class Test_Autoloader extends Base {

	/**
	 * Retrieve the GatherPress autoloader closure from the SPL stack.
	 *
	 * @return Closure|null The registered autoloader closure, or null if not found.
	 */
	protected function get_autoloader(): ?Closure {
		$autoloaders = spl_autoload_functions();

		if ( ! is_array( $autoloaders ) ) {
			return null;
		}

		foreach ( $autoloaders as $autoloader ) {
			if ( $autoloader instanceof Closure ) {
				$reflection = new ReflectionFunction( $autoloader );
				$file_name  = (string) $reflection->getFileName();

				if ( str_contains( $file_name, 'class-autoloader.php' ) ) {
					return $autoloader;
				}
			}
		}

		return null;
	}

	/**
	 * Test that register adds an autoloader closure to the SPL stack.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_adds_spl_autoloader(): void {
		$autoloader = $this->get_autoloader();

		$this->assertInstanceOf(
			Closure::class,
			$autoloader,
			'Autoloader::register() should have registered a Closure in the SPL autoload stack.'
		);
	}

	/**
	 * Test that empty class strings or whitespace strings are ignored safely.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_ignores_empty_or_whitespace_class_string(): void {
		$autoloader = $this->get_autoloader();

		$this->assertNotNull( $autoloader );

		$autoloader( '' );
		$autoloader( '   ' );
		$autoloader( '\\' );

		$this->assertTrue( true, 'Empty class strings should be safely ignored.' );
	}

	/**
	 * Test that classes without namespaces (global classes) are ignored.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_ignores_class_without_namespace(): void {
		$autoloader = $this->get_autoloader();

		$this->assertNotNull( $autoloader );

		$autoloader( 'Global_Class_Name' );
		$autoloader( 'stdClass' );

		$this->assertTrue( true, 'Global classes without namespaces should be ignored.' );
	}

	/**
	 * Test that classes in unrelated namespaces are ignored.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_ignores_unrelated_namespace(): void {
		$autoloader = $this->get_autoloader();

		$this->assertNotNull( $autoloader );

		$autoloader( 'WordPress\Plugin\Some_Class' );
		$autoloader( 'Acme\Package\My_Class' );

		$this->assertFalse(
			class_exists( 'WordPress\Plugin\Some_Class', false ),
			'Unrelated namespaces should not be loaded.'
		);
	}

	/**
	 * Test that classes matching namespace prefix without trailing delimiter are ignored.
	 *
	 * For instance, 'GatherPressExtra\Class' when root is 'GatherPress\'.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_ignores_partial_namespace_prefix(): void {
		$autoloader = $this->get_autoloader();

		$this->assertNotNull( $autoloader );

		$autoloader( 'GatherPressExtra\Sub\My_Class' );

		$this->assertFalse(
			class_exists( 'GatherPressExtra\Sub\My_Class', false ),
			'Partial namespace prefixes should not trigger autoloading.'
		);
	}

	/**
	 * Test that leading backslashes are trimmed properly and class is successfully loaded.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_trims_leading_backslash(): void {
		$fixture_root = dirname( __DIR__, 4 ) . '/fixtures/autoloader/layout-a';

		$filter_callback = static function ( array $namespaces ) use ( $fixture_root ): array {
			$namespaces['GatherPress_Autoloader_Fixture'] = $fixture_root;
			return $namespaces;
		};

		add_filter( 'gatherpress_autoloader', $filter_callback );

		$autoloader = $this->get_autoloader();

		$this->assertNotNull( $autoloader );

		$autoloader( '\GatherPress_Autoloader_Fixture\Core\Fixture_Leading_Backslash' );

		$this->assertTrue(
			class_exists( 'GatherPress_Autoloader_Fixture\Core\Fixture_Leading_Backslash', false ),
			'Class with leading backslash should be trimmed, resolved, and loaded successfully.'
		);

		// Verify non-existent class with leading backslash safely fails without errors.
		$autoloader( '\GatherPress\NonExistent\Dummy_Class' );

		$this->assertFalse(
			class_exists( '\GatherPress\NonExistent\Dummy_Class', false ),
			'Non-existent class with leading backslash should safely fail to load.'
		);

		remove_filter( 'gatherpress_autoloader', $filter_callback );
	}

	/**
	 * Test that nonexistent class within GatherPress namespace does not error or throw.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_nonexistent_class_does_not_throw(): void {
		$autoloader = $this->get_autoloader();

		$this->assertNotNull( $autoloader );

		$autoloader( 'GatherPress\Core\Non_Existent_Class_Test_XYZ' );

		$this->assertFalse(
			class_exists( 'GatherPress\Core\Non_Existent_Class_Test_XYZ', false ),
			'Nonexistent class should return false without errors.'
		);
	}

	/**
	 * Test that custom namespaces can be registered via the gatherpress_autoloader filter (Layout A).
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_custom_namespace_filter_layout_a(): void {
		$fixture_root = dirname( __DIR__, 4 ) . '/fixtures/autoloader/layout-a';

		$filter_callback = static function ( array $namespaces ) use ( $fixture_root ): array {
			$namespaces['GatherPress_Autoloader_Fixture'] = $fixture_root;
			return $namespaces;
		};

		add_filter( 'gatherpress_autoloader', $filter_callback );

		$class_name = 'GatherPress_Autoloader_Fixture\Core\Fixture_Layout_A';

		$this->assertTrue(
			class_exists( $class_name ),
			'Autoloader should resolve and load class from custom registered namespace (Layout A).'
		);

		remove_filter( 'gatherpress_autoloader', $filter_callback );
	}

	/**
	 * Test nested subdirectories and underscore to hyphen conversion in class and directory names.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_nested_subdirectories_and_hyphen_conversion(): void {
		$fixture_root = dirname( __DIR__, 4 ) . '/fixtures/autoloader/layout-a';

		$filter_callback = static function ( array $namespaces ) use ( $fixture_root ): array {
			$namespaces['GatherPress_Autoloader_Fixture'] = $fixture_root;
			return $namespaces;
		};

		add_filter( 'gatherpress_autoloader', $filter_callback );

		$class_name = 'GatherPress_Autoloader_Fixture\Core\Sub_Dir\Nested_Class';

		$this->assertTrue(
			class_exists( $class_name ),
			'Autoloader should resolve nested subdirectories with converted hyphens.'
		);

		remove_filter( 'gatherpress_autoloader', $filter_callback );
	}

	/**
	 * Test that test layout (Layout B) classes are resolved and loaded correctly.
	 *
	 * In Layout B, 'classes' lands at the end of the namespace path directly before the filename.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_layout_b_test_structure_resolution(): void {
		$fixture_root = dirname( __DIR__, 4 ) . '/fixtures/autoloader/layout-b';

		$filter_callback = static function ( array $namespaces ) use ( $fixture_root ): array {
			$namespaces['GatherPress_Autoloader_Fixture_B'] = $fixture_root;
			return $namespaces;
		};

		add_filter( 'gatherpress_autoloader', $filter_callback );

		$class_name = 'GatherPress_Autoloader_Fixture_B\Tests\Fixture_Layout_B';

		$this->assertTrue(
			class_exists( $class_name ),
			'Autoloader should resolve and load class following Layout B test structure.'
		);

		remove_filter( 'gatherpress_autoloader', $filter_callback );
	}

	/**
	 * Test that validate_file blocks invalid or traversal paths.
	 *
	 * @since TBD
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_validate_file_blocks_invalid_paths(): void {
		$fixture_root = dirname( __DIR__, 4 ) . '/fixtures/autoloader/layout-a';
		$invalid_path = $fixture_root . '/../layout-a';

		$filter_callback = static function ( array $namespaces ) use ( $invalid_path ): array {
			$namespaces['GatherPress_Traversal_Fixture'] = $invalid_path;
			return $namespaces;
		};

		add_filter( 'gatherpress_autoloader', $filter_callback );

		$class_name = 'GatherPress_Traversal_Fixture\Core\Fixture_Traversal';

		$this->assertFalse(
			class_exists( $class_name ),
			'Invalid or traversal paths should be blocked by validate_file.'
		);

		remove_filter( 'gatherpress_autoloader', $filter_callback );
	}
}
