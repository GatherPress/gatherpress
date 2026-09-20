<?php
/**
 * Class handles unit tests for the Subscribe to Events block.
 *
 * The block has no PHP class of its own — render.php resolves the feed URL
 * through GatherPress\Core\Calendar\Feed_Url and prints the markup — so these
 * tests drive it through the block registry the way the frontend does.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Calendar\Feed_Url;
use GatherPress\Core\Calendar\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Subscribe_To_Events.
 *
 * @group endpoints
 */
class Test_Subscribe_To_Events extends Base {

	/**
	 * The block name under test.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/subscribe-to-events';

	/**
	 * Coverage for the sitewide scope rendering both links.
	 *
	 * @return void
	 */
	public function test_render_sitewide_scope_renders_both_links(): void {
		$output = do_blocks( sprintf( '<!-- wp:%s /-->', self::BLOCK_NAME ) );

		$feed_url = get_feed_link( Setup::ICAL_SLUG );

		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( $feed_url ) ),
			$output,
			'The iCal link should point at the sitewide feed.'
		);
		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( Feed_Url::to_webcal( $feed_url ) ) ),
			$output,
			'The subscribe link should point at the webcal form of the feed.'
		);
	}

	/**
	 * Coverage for the linkFormat attribute.
	 *
	 * @return void
	 */
	public function test_render_respects_link_format(): void {
		$ical_only = do_blocks(
			sprintf( '<!-- wp:%s {"linkFormat":"ical"} /-->', self::BLOCK_NAME )
		);

		$this->assertStringContainsString(
			'iCal feed',
			$ical_only,
			'The iCal-only format should render the iCal link.'
		);
		$this->assertStringNotContainsString(
			'webcal://',
			$ical_only,
			'The iCal-only format should not render the subscribe link.'
		);

		$webcal_only = do_blocks(
			sprintf( '<!-- wp:%s {"linkFormat":"webcal"} /-->', self::BLOCK_NAME )
		);

		$this->assertStringContainsString(
			'webcal://',
			$webcal_only,
			'The subscribe-only format should render the subscribe link.'
		);
		$this->assertStringNotContainsString(
			sprintf( 'href="%s"', esc_url( get_feed_link( Setup::ICAL_SLUG ) ) ),
			$webcal_only,
			'The subscribe-only format should not render the plain iCal link.'
		);
	}

	/**
	 * Coverage for the custom link labels.
	 *
	 * @return void
	 */
	public function test_render_uses_custom_link_text(): void {
		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"linkText":"Grab the feed","subscribeText":"Follow us"} /-->',
				self::BLOCK_NAME
			)
		);

		$this->assertStringContainsString( 'Grab the feed', $output, 'The custom iCal label should render.' );
		$this->assertStringContainsString( 'Follow us', $output, 'The custom subscribe label should render.' );
	}

	/**
	 * Coverage for the archive scope.
	 *
	 * @return void
	 */
	public function test_render_archive_scope(): void {
		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"scope":"archive","postType":"%s"} /-->',
				self::BLOCK_NAME,
				Event::POST_TYPE
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'href="%s"',
				esc_url( get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ) )
			),
			$output,
			'The archive scope should link to the post type archive feed.'
		);
	}

	/**
	 * Coverage for the venue scope.
	 *
	 * @return void
	 */
	public function test_render_venue_scope(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"scope":"venue","venueId":%d} /-->',
				self::BLOCK_NAME,
				$venue->ID
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'href="%s"',
				esc_url( get_post_comments_feed_link( $venue->ID, Setup::ICAL_SLUG ) )
			),
			$output,
			'The venue scope should link to the venue feed.'
		);
	}

	/**
	 * Coverage for a venue scope with no venue selected.
	 *
	 * The docs say a scope that needs an ID renders nothing. `get_post( 0 )`
	 * returns the global post, so a venue page must not leak its own feed.
	 *
	 * @return void
	 */
	public function test_render_venue_scope_without_a_venue(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$this->go_to( get_permalink( $venue->ID ) );

		$output = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"venue"} /-->', self::BLOCK_NAME )
		);

		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$output,
			'A venue scope with no venue selected should render no wrapper.'
		);
	}

	/**
	 * Coverage for the topic scope.
	 *
	 * @return void
	 */
	public function test_render_topic_scope(): void {
		$term = $this->factory->term->create_and_get( array( 'taxonomy' => Topic::TAXONOMY ) );

		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"scope":"topic","topicId":%d} /-->',
				self::BLOCK_NAME,
				$term->term_id
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'href="%s"',
				esc_url( get_term_feed_link( $term->term_id, Topic::TAXONOMY, Setup::ICAL_SLUG ) )
			),
			$output,
			'The topic scope should link to the term feed.'
		);
	}

	/**
	 * Coverage for scopes that cannot resolve, which render nothing.
	 *
	 * @return void
	 */
	public function test_render_returns_nothing_for_unresolvable_scopes(): void {
		$venue_scope = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"venue","venueId":999999999} /-->', self::BLOCK_NAME )
		);
		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$venue_scope,
			'A venue scope pointing at a missing post should render no wrapper.'
		);

		$topic_scope = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"topic","topicId":999999999} /-->', self::BLOCK_NAME )
		);
		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$topic_scope,
			'A topic scope pointing at a missing term should render no wrapper.'
		);

		$bad_scope = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"nope"} /-->', self::BLOCK_NAME )
		);
		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$bad_scope,
			'An unknown scope should render no wrapper.'
		);
	}

	/**
	 * Coverage for the filter reaching the block output.
	 *
	 * @return void
	 */
	public function test_render_honors_the_feed_url_filter(): void {
		$callback = function (): string {
			return 'https://example.org/filtered-feed/';
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$output = do_blocks( sprintf( '<!-- wp:%s /-->', self::BLOCK_NAME ) );

		remove_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertStringContainsString(
			'href="https://example.org/filtered-feed/"',
			$output,
			'The resolved feed URL should pass through the gatherpress_calendar_feed_url filter.'
		);
	}

	/**
	 * Coverage for the filter supplying a feed URL for a scope core does not know.
	 *
	 * @return void
	 */
	public function test_render_honors_the_feed_url_filter_for_unknown_scope(): void {
		$callback = function (): string {
			return 'https://example.org/companion-feed/';
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$output = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"companion"} /-->', self::BLOCK_NAME )
		);

		remove_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertStringContainsString(
			'href="https://example.org/companion-feed/"',
			$output,
			'The block should render a feed URL the filter supplies for an unknown scope.'
		);
	}
}
