<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Feed_Url.
 *
 * @package GatherPress\Core\Calendar
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Feed_Url;
use GatherPress\Core\Calendar\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Feed_Url.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Feed_Url
 * @group              endpoints
 */
class Test_Feed_Url extends Base {

	/**
	 * Coverage for get() with the sitewide scope.
	 *
	 * @covers ::get
	 * @covers ::resolve
	 * @covers ::sitewide_url
	 *
	 * @return void
	 */
	public function test_get_sitewide_scope(): void {
		$this->assertSame(
			get_feed_link( Setup::ICAL_SLUG ),
			Feed_Url::get( array( 'scope' => 'sitewide' ) ),
			'Failed to assert the sitewide scope resolves to the sitewide calendar feed.'
		);
	}

	/**
	 * Coverage for get() defaulting to the sitewide scope.
	 *
	 * @covers ::get
	 * @covers ::resolve
	 * @covers ::sitewide_url
	 *
	 * @return void
	 */
	public function test_get_defaults_to_sitewide_scope(): void {
		$this->assertSame(
			get_feed_link( Setup::ICAL_SLUG ),
			Feed_Url::get( array() ),
			'Failed to assert an empty args array resolves to the sitewide calendar feed.'
		);
	}

	/**
	 * Coverage for get() with the archive scope.
	 *
	 * @covers ::get
	 * @covers ::resolve
	 * @covers ::archive_url
	 *
	 * @return void
	 */
	public function test_get_archive_scope(): void {
		$this->assertSame(
			get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ),
			Feed_Url::get(
				array(
					'scope'     => 'archive',
					'post_type' => Event::POST_TYPE,
				)
			),
			'Failed to assert the archive scope resolves to the post type archive feed.'
		);
	}

	/**
	 * Coverage for get() with an empty post type falling back to the primary one.
	 *
	 * @covers ::get
	 * @covers ::archive_url
	 * @covers ::primary_event_post_type
	 *
	 * @return void
	 */
	public function test_get_archive_scope_defaults_to_primary_event_post_type(): void {
		$this->assertSame(
			get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ),
			Feed_Url::get( array( 'scope' => 'archive' ) ),
			'Failed to assert an empty post type falls back to the primary event post type.'
		);
	}

	/**
	 * Coverage for get() with the archive scope and a post type that carries no events.
	 *
	 * @covers ::get
	 * @covers ::archive_url
	 *
	 * @return void
	 */
	public function test_get_archive_scope_rejects_non_event_post_type(): void {
		$this->assertFalse(
			Feed_Url::get(
				array(
					'scope'     => 'archive',
					'post_type' => 'page',
				)
			),
			'Failed to assert a post type without event dates resolves to false.'
		);
	}

	/**
	 * Coverage for get() with the venue scope.
	 *
	 * @covers ::get
	 * @covers ::resolve
	 * @covers ::venue_url
	 * @covers ::is_tax_like_type_for_event_supporting_types
	 * @covers ::has_post_type_for_taxonomy
	 *
	 * @return void
	 */
	public function test_get_venue_scope(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		$this->assertSame(
			get_post_comments_feed_link( $venue->ID, Setup::ICAL_SLUG ),
			Feed_Url::get(
				array(
					'scope'    => 'venue',
					'venue_id' => $venue->ID,
				)
			),
			'Failed to assert the venue scope resolves to the venue feed.'
		);
	}

	/**
	 * Coverage for get() with the venue scope and a post that is not a venue.
	 *
	 * @covers ::get
	 * @covers ::venue_url
	 * @covers ::is_tax_like_type_for_event_supporting_types
	 *
	 * @return void
	 */
	public function test_get_venue_scope_rejects_non_venue_post(): void {
		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		$this->assertFalse(
			Feed_Url::get(
				array(
					'scope'    => 'venue',
					'venue_id' => $post->ID,
				)
			),
			'Failed to assert a non-venue post resolves to false.'
		);
	}

	/**
	 * Coverage for get() with the venue scope and an unpublished venue post.
	 *
	 * @covers ::get
	 * @covers ::venue_url
	 * @covers ::is_tax_like_type_for_event_supporting_types
	 *
	 * @return void
	 */
	public function test_get_venue_scope_rejects_unpublished_venue(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
			)
		)->get();

		$this->assertFalse(
			Feed_Url::get(
				array(
					'scope'    => 'venue',
					'venue_id' => $venue->ID,
				)
			),
			'Failed to assert an unpublished venue resolves to false.'
		);
	}

	/**
	 * Coverage for get() with the venue scope and an ID that does not exist.
	 *
	 * @covers ::get
	 * @covers ::venue_url
	 *
	 * @return void
	 */
	public function test_get_venue_scope_rejects_missing_post(): void {
		$this->assertFalse(
			Feed_Url::get(
				array(
					'scope'    => 'venue',
					'venue_id' => 999999999,
				)
			),
			'Failed to assert a missing venue post resolves to false.'
		);
	}

	/**
	 * Coverage for get() with the venue scope and no venue selected.
	 *
	 * `get_post( 0 )` returns the global post, so a venue page must still
	 * resolve to false rather than rendering its own feed.
	 *
	 * @covers ::get
	 * @covers ::resolve
	 * @covers ::venue_url
	 *
	 * @return void
	 */
	public function test_get_venue_scope_rejects_zero_venue_id(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$this->go_to( get_permalink( $venue->ID ) );

		$this->assertFalse(
			Feed_Url::get( array( 'scope' => 'venue' ) ),
			'Failed to assert an unselected venue resolves to false on a venue page.'
		);
	}

	/**
	 * Coverage for get() with the topic scope.
	 *
	 * @covers ::get
	 * @covers ::resolve
	 * @covers ::topic_url
	 * @covers ::has_post_type_for_taxonomy
	 *
	 * @return void
	 */
	public function test_get_topic_scope(): void {
		$term = $this->factory->term->create_and_get( array( 'taxonomy' => Topic::TAXONOMY ) );

		$this->assertSame(
			get_term_feed_link( $term->term_id, Topic::TAXONOMY, Setup::ICAL_SLUG ),
			Feed_Url::get(
				array(
					'scope'    => 'topic',
					'topic_id' => $term->term_id,
				)
			),
			'Failed to assert the topic scope resolves to the term feed.'
		);
	}

	/**
	 * Coverage for get() with the topic scope and a term of another taxonomy.
	 *
	 * @covers ::get
	 * @covers ::topic_url
	 * @covers ::has_post_type_for_taxonomy
	 *
	 * @return void
	 */
	public function test_get_topic_scope_rejects_term_without_events(): void {
		$term = $this->factory->term->create_and_get( array( 'taxonomy' => 'category' ) );

		$this->assertFalse(
			Feed_Url::get(
				array(
					'scope'    => 'topic',
					'topic_id' => $term->term_id,
				)
			),
			'Failed to assert a term from a non-event taxonomy resolves to false.'
		);
	}

	/**
	 * Coverage for get() with the topic scope and an ID that does not exist.
	 *
	 * @covers ::get
	 * @covers ::topic_url
	 *
	 * @return void
	 */
	public function test_get_topic_scope_rejects_missing_term(): void {
		$this->assertFalse(
			Feed_Url::get(
				array(
					'scope'    => 'topic',
					'topic_id' => 999999999,
				)
			),
			'Failed to assert a missing topic term resolves to false.'
		);
	}

	/**
	 * Coverage for get() with an unknown scope.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_rejects_unknown_scope(): void {
		$this->assertFalse(
			Feed_Url::get( array( 'scope' => 'nope' ) ),
			'Failed to assert an unknown scope resolves to false.'
		);
	}

	/**
	 * Coverage for get() with a non-string scope.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_rejects_non_string_scope(): void {
		$this->assertFalse(
			Feed_Url::get( array( 'scope' => array( 'sitewide' ) ) ),
			'Failed to assert a non-string scope resolves to false.'
		);
	}

	/**
	 * Coverage for the gatherpress_calendar_feed_url filter.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_applies_filter(): void {
		$callback = function (): string {
			return 'https://example.org/custom-feed/';
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertSame(
			'https://example.org/custom-feed/',
			Feed_Url::get( array( 'scope' => 'sitewide' ) ),
			'Failed to assert the filter replaces the resolved feed URL.'
		);

		remove_filter( 'gatherpress_calendar_feed_url', $callback );
	}

	/**
	 * Coverage for the gatherpress_calendar_feed_url filter returning a non-string.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_rejects_non_string_filter_result(): void {
		$callback = function (): bool {
			return true;
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertFalse(
			Feed_Url::get( array( 'scope' => 'sitewide' ) ),
			'Failed to assert a non-string filter result resolves to false.'
		);

		remove_filter( 'gatherpress_calendar_feed_url', $callback );
	}

	/**
	 * Coverage for the gatherpress_calendar_feed_url filter supplying a URL for an
	 * unknown scope.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_filter_can_supply_url_for_unknown_scope(): void {
		$callback = function (): string {
			return 'https://example.org/companion-feed/';
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertSame(
			'https://example.org/companion-feed/',
			Feed_Url::get( array( 'scope' => 'companion' ) ),
			'Failed to assert the filter can resolve a scope core does not know about.'
		);

		remove_filter( 'gatherpress_calendar_feed_url', $callback );
	}

	/**
	 * Coverage for to_webcal across the schemes it may receive.
	 *
	 * @covers ::to_webcal
	 *
	 * @return void
	 */
	public function test_to_webcal(): void {
		$this->assertSame(
			'webcal://example.org/feed/ical',
			Feed_Url::to_webcal( 'https://example.org/feed/ical' ),
			'Failed to assert an https URL is rewritten to webcal.'
		);

		$this->assertSame(
			'webcal://example.org/feed/ical',
			Feed_Url::to_webcal( 'http://example.org/feed/ical' ),
			'Failed to assert an http URL is rewritten to webcal.'
		);

		$this->assertSame(
			'webcal://example.org/feed/ical',
			Feed_Url::to_webcal( 'webcal://example.org/feed/ical' ),
			'Failed to assert an already-webcal URL is left alone.'
		);

		$this->assertSame(
			'/feed/ical',
			Feed_Url::to_webcal( '/feed/ical' ),
			'Failed to assert a relative path is left alone.'
		);

		$this->assertSame(
			'',
			Feed_Url::to_webcal( '' ),
			'Failed to assert an empty string is left alone.'
		);
	}

	/**
	 * Coverage for has_post_type_for_taxonomy.
	 *
	 * @covers ::has_post_type_for_taxonomy
	 *
	 * @return void
	 */
	public function test_has_post_type_for_taxonomy(): void {
		$this->assertTrue(
			Feed_Url::has_post_type_for_taxonomy( Topic::TAXONOMY ),
			'Failed to assert the topic taxonomy is attached to an event post type.'
		);

		$this->assertFalse(
			Feed_Url::has_post_type_for_taxonomy( 'category' ),
			'Failed to assert category is not attached to an event post type.'
		);
	}

	/**
	 * Coverage for is_tax_like_type_for_event_supporting_types.
	 *
	 * @covers ::is_tax_like_type_for_event_supporting_types
	 * @covers ::has_post_type_for_taxonomy
	 *
	 * @return void
	 */
	public function test_is_tax_like_type_for_event_supporting_types(): void {
		$this->assertTrue(
			Feed_Url::is_tax_like_type_for_event_supporting_types( Venue::POST_TYPE ),
			'Failed to assert the venue post type is a tax-like shadow source for events.'
		);

		$this->assertFalse(
			Feed_Url::is_tax_like_type_for_event_supporting_types( 'page' ),
			'Failed to assert page is not a tax-like shadow source for events.'
		);
	}
}
