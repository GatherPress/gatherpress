<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Response\Provider_Registry.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
use GatherPress\Tests\Base;
use ReflectionClass;
use InvalidArgumentException;

/**
 * Class Test_Provider_Registry.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\Provider_Registry
 */
class Test_Provider_Registry extends Base {

	/**
	 * Build a throwaway provider with the given slug.
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return Provider
	 */
	protected function make_provider( string $slug ): Provider {
		return new class( $slug ) extends Provider {

			/**
			 * Slug shared with the static accessor.
			 *
			 * @var string
			 */
			public static string $test_slug = '';

			/**
			 * Store the slug for the static accessor.
			 *
			 * @param string $slug Provider slug.
			 */
			public function __construct( string $slug ) {
				self::$test_slug = $slug;
			}

			/**
			 * Provider slug.
			 *
			 * @return string
			 */
			public static function get_slug(): string {
				return self::$test_slug;
			}

			/**
			 * Identity type handled by this provider.
			 *
			 * @return Identity_Type
			 */
			public static function get_identity_type(): Identity_Type {
				return Identity_Type::EXTERNAL_ID;
			}

			/**
			 * Human label.
			 *
			 * @return string
			 */
			public static function get_label(): string {
				return 'Unit Test Provider';
			}

			/**
			 * Display name for an identity.
			 *
			 * @param Identity $identity The identity.
			 *
			 * @return string
			 */
			public function get_display_name( Identity $identity ): string {
				return 'External #' . $identity->value;
			}
		};
	}

	/**
	 * The core providers registered on gatherpress_loaded are queryable
	 * through every read accessor.
	 *
	 * @covers ::register_rsvp_providers
	 * @covers ::register_core_types
	 * @covers ::is_registered
	 * @covers ::get
	 * @covers ::get_all
	 * @covers ::get_slugs
	 * @covers ::from_slug
	 *
	 * @return void
	 */
	public function test_core_providers_are_registered_and_readable(): void {
		$registry = Provider_Registry::get_instance();

		$registry->register_rsvp_providers();

		$this->assertTrue( $registry->is_registered( 'user' ) );
		$this->assertTrue( $registry->is_registered( 'email' ) );
		$this->assertInstanceOf( User::class, $registry->get( 'user' ) );
		$this->assertInstanceOf( Email::class, $registry->get( 'email' ) );
		$this->assertNull( $registry->get( 'nonexistent' ) );

		$slugs = $registry->get_slugs();
		$this->assertContains( 'user', $slugs );
		$this->assertContains( 'email', $slugs );

		$all = $registry->get_all();
		$this->assertArrayHasKey( 'user', $all );
		$this->assertArrayHasKey( 'email', $all );

		$this->assertInstanceOf( Email::class, Provider_Registry::from_slug( 'email' ) );
		$this->assertNull( Provider_Registry::from_slug( 'nonexistent' ) );
	}

	/**
	 * Registration accepts a new provider once, refuses the duplicate,
	 * and rejects slugs that are too short.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_validates_and_deduplicates(): void {
		$registry = Provider_Registry::get_instance();
		$provider = $this->make_provider( 'unit-test-provider' );

		$this->assertTrue( $registry->register( $provider ), 'A new provider registers.' );
		$this->assertFalse( $registry->register( $provider ), 'A duplicate slug is refused.' );
		$this->assertTrue( $registry->is_registered( 'unit-test-provider' ) );

		$this->expectException( InvalidArgumentException::class );

		$registry->register( $this->make_provider( 'abc' ) );
	}

	/**
	 * Constructing the registry wires the gatherpress_loaded listener and
	 * registers the core providers. The bootstrap instance predates
	 * coverage collection, so force a fresh construction.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 * @covers ::register_core_types
	 *
	 * @return void
	 */
	public function test_constructor_wires_hooks_and_core_types(): void {
		$reflection = new ReflectionClass( Provider_Registry::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );

		$original = $property->getValue();
		$property->setValue( null, null );

		$registry = Provider_Registry::get_instance();

		$this->assertSame(
			PHP_INT_MIN,
			has_action( 'gatherpress_loaded', array( $registry, 'register_rsvp_providers' ) ),
			'Construction hooks provider registration onto gatherpress_loaded as early as possible.'
		);
		$this->assertTrue( $registry->is_registered( 'user' ), 'Core user provider registers at construction.' );
		$this->assertTrue( $registry->is_registered( 'email' ), 'Core email provider registers at construction.' );

		remove_action( 'gatherpress_loaded', array( $registry, 'register_rsvp_providers' ), PHP_INT_MIN );
		$property->setValue( null, $original );
	}
}
