<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue.
 *
 * Covers the instance class — constructor and per-post accessors. Anything
 * that isn't tied to a specific venue instance (WP integration, post-type-level
 * utilities, lookups) is covered by `Test_Venue_Setup`.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use WP_Term;

/**
 * Class Test_Venue.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue
 */
class Test_Venue extends Base {
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
	 * Returns parsed venue information when JSON meta is present.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_reads_meta_json(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);
		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '123 Main St',
					'phoneNumber' => '555-0100',
					'website'     => 'https://example.com',
					'latitude'    => '40.7128',
					'longitude'   => '-74.0060',
				)
			)
		);

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame( '123 Main St', $info['fullAddress'] );
		$this->assertSame( '555-0100', $info['phoneNumber'] );
		$this->assertSame( 'https://example.com', $info['website'] );
		$this->assertSame( '40.7128', $info['latitude'] );
		$this->assertSame( '-74.0060', $info['longitude'] );
	}

	/**
	 * Returns a fully-populated empty-string shape when no JSON meta exists.
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
			array(
				'fullAddress' => '',
				'phoneNumber' => '',
				'website'     => '',
				'latitude'    => '',
				'longitude'   => '',
			),
			$info,
			'Failed to assert the empty-state default shape when no venue meta is stored.'
		);
	}

	/**
	 * Malformed JSON meta falls back to the empty shape rather than throwing.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_handles_invalid_json(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', 'not json' );

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame( '', $info['fullAddress'] );
		$this->assertSame( '', $info['latitude'] );
		$this->assertSame( '', $info['longitude'] );
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
	 * get_taxonomy returns '' when wrapping a non-venue post.
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
	 * get_term_slug returns '' when wrapping a non-venue post.
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
	 * get_term returns null when wrapping a non-venue post.
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
	 * get_information returns the default empty shape when wrapping a non-venue post.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_returns_empty_shape_for_unsupported_post(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->assertSame(
			array(
				'fullAddress' => '',
				'phoneNumber' => '',
				'website'     => '',
				'latitude'    => '',
				'longitude'   => '',
			),
			( new Venue( $post_id ) )->get_information()
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
