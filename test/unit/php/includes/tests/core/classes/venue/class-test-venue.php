<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue\Venue.
 *
 * Covers the instance class — constructor and per-post accessors. Anything
 * that isn't tied to a specific venue instance (WP integration, post-type-level
 * utilities, lookups) is covered by `Test_Setup`.
 *
 * @package GatherPress\Core\Venue
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Venue\Meta;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use ReflectionClass;
use WP_Term;

/**
 * Class Test_Venue.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue\Venue
 */
class Test_Venue extends Base {
	/**
	 * Asserts that the prior fully-qualified class name `GatherPress\Core\Venue` continues
	 * to resolve to the current class `GatherPress\Core\Venue\Venue` via the alias map in
	 * `includes/core/register-class-aliases.php`. Removing the alias entry would silently
	 * break external consumers (other plugins, theme code) that reference the prior FQN —
	 * this test fails loudly first.
	 *
	 * @return void
	 */
	public function test_prior_fqn_resolves_to_current_class(): void {
		$prior_fqn = 'GatherPress\\Core\\Venue';

		$this->assertTrue(
			class_exists( $prior_fqn ),
			'The prior fully-qualified class name should resolve via the alias map.'
		);

		$reflection = new ReflectionClass( $prior_fqn );
		$this->assertSame(
			Venue::class,
			$reflection->getName(),
			'The prior FQN should resolve to the current Venue class.'
		);

		// Read a class constant through the prior FQN to confirm runtime usability.
		$this->assertSame( Venue::POST_TYPE, constant( $prior_fqn . '::POST_TYPE' ) );
	}

	/**
	 * Construct a Venue instance from a post ID and read it back.
	 *
	 * @covers ::__construct
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_construct_and_get_post_id(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$venue = new Venue( $post_id );

		$this->assertSame(
			$post_id,
			$venue->get_post_id(),
			'Failed to assert that the Venue exposes the post ID it was constructed with.'
		);
	}

	/**
	 * Returns the venue information when individual meta keys are populated.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_reads_individual_meta(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);
		add_post_meta( $post_id, 'gatherpress_address', '123 Main St' );
		add_post_meta( $post_id, 'gatherpress_phone', '555-0100' );
		add_post_meta( $post_id, 'gatherpress_website', 'https://example.com' );
		add_post_meta( $post_id, 'gatherpress_latitude', '40.7128' );
		add_post_meta( $post_id, 'gatherpress_longitude', '-74.006' );

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame( '123 Main St', $info['address'] );
		$this->assertSame( '555-0100', $info['phone'] );
		$this->assertSame( 'https://example.com', $info['website'] );
		$this->assertSame( '40.7128', $info['latitude'] );
		$this->assertSame( '-74.006', $info['longitude'] );
	}

	/**
	 * Returns a fully-populated empty-string shape when no venue meta exists.
	 *
	 * The array shape is stable so callers can index into it without guards.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_returns_empty_shape_when_meta_absent(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame(
			$this->empty_information_shape(),
			$info,
			'Failed to assert the empty-state default shape when no venue meta is stored.'
		);
	}


	/**
	 * Coverage for get_term_slug — derives the slug from the instance's post_name.
	 *
	 * @covers ::get_term_slug
	 *
	 * @return void
	 */
	public function test_get_term_slug(): void {
		$venue = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'unit-test',
			)
		)->get();

		$this->assertSame(
			'_unit-test',
			( new Venue( $venue->ID ) )->get_term_slug(),
			'Failed to assert that term slug is derived from the venue post_name.'
		);
	}

	/**
	 * Constructor leaves `$venue` null when the post type doesn't declare
	 * gatherpress-venue-information support, so callers can guard safely.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_leaves_venue_null_for_unsupported_post_type(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$venue = new Venue( $post_id );

		$this->assertNull(
			$venue->venue,
			'Expected $venue property to be null when post type lacks gatherpress-venue-information support.'
		);
		$this->assertSame( 0, $venue->get_post_id() );
		$this->assertSame( '', $venue->get_post_type() );
	}

	/**
	 * Returns '' from get_taxonomy when wrapping a non-venue post.
	 *
	 * @covers ::get_taxonomy
	 *
	 * @return void
	 */
	public function test_get_taxonomy_returns_empty_for_unsupported_post(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->assertSame( '', ( new Venue( $post_id ) )->get_taxonomy() );
	}

	/**
	 * Returns '' from get_term_slug when wrapping a non-venue post.
	 *
	 * @covers ::get_term_slug
	 *
	 * @return void
	 */
	public function test_get_term_slug_returns_empty_for_unsupported_post(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->assertSame( '', ( new Venue( $post_id ) )->get_term_slug() );
	}

	/**
	 * Returns null from get_term when wrapping a non-venue post.
	 *
	 * @covers ::get_term
	 *
	 * @return void
	 */
	public function test_get_term_returns_null_for_unsupported_post(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->assertNull( ( new Venue( $post_id ) )->get_term() );
	}

	/**
	 * Returns the default empty shape from get_information when wrapping a non-venue post.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_returns_empty_shape_for_unsupported_post(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->assertSame(
			$this->empty_information_shape(),
			( new Venue( $post_id ) )->get_information()
		);
	}

	/**
	 * Returns the expected empty-string shape for `get_information()`.
	 *
	 * Derived from the same source of truth (`Meta::STRUCTURED_ADDRESS_FIELDS`)
	 * the production code uses, so adding a new structured field updates both
	 * sides simultaneously and the assertion can't silently drift.
	 *
	 * @return array<string, string>
	 */
	private function empty_information_shape(): array {
		return array_fill_keys(
			array_merge( Meta::EDITOR_WRITABLE_FIELDS, Meta::STRUCTURED_ADDRESS_FIELDS ),
			''
		);
	}

	/**
	 * Returns the post type slug of the wrapped venue.
	 *
	 * @covers ::get_post_type
	 *
	 * @return void
	 */
	public function test_get_post_type(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$this->assertSame(
			Venue::POST_TYPE,
			( new Venue( $post_id ) )->get_post_type(),
			'Failed to assert that get_post_type returns the wrapped post type.'
		);
	}

	/**
	 * Returns the taxonomy derived from the wrapped venue's post type.
	 *
	 * @covers ::get_taxonomy
	 *
	 * @return void
	 */
	public function test_get_taxonomy(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$this->assertSame(
			Venue::TAXONOMY,
			( new Venue( $post_id ) )->get_taxonomy(),
			'Failed to assert that get_taxonomy returns the taxonomy for the wrapped venue.'
		);
	}

	/**
	 * Returns the existing taxonomy term for the wrapped venue.
	 *
	 * @covers ::get_term
	 *
	 * @return void
	 */
	public function test_get_term_returns_existing_term(): void {
		$post = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'term-lookup-venue',
				'post_title' => 'Term Lookup Venue',
			)
		)->get();

		$venue = new Venue( $post->ID );
		$term  = $venue->get_term();

		$this->assertInstanceOf( WP_Term::class, $term, 'Expected get_term to return a WP_Term instance.' );
		$this->assertSame( '_term-lookup-venue', $term->slug, 'Expected term slug to match the venue.' );
	}

	/**
	 * Returns null when no taxonomy term exists for the wrapped venue.
	 *
	 * @covers ::get_term
	 *
	 * @return void
	 */
	public function test_get_term_returns_null_when_missing(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_name'   => 'no-term-yet',
				'post_status' => 'draft',
			)
		);

		$this->assertNull(
			( new Venue( $post_id ) )->get_term(),
			'Expected get_term to return null when no matching term exists.'
		);
	}
}
