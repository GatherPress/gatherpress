<?php
/**
 * Test class for RSVP Abilities.
 *
 * @package GatherPress\Tests\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event\Abilities as Event_Abilities;
use GatherPress\Core\Rsvp\Abilities;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities.
 *
 * @since 0.36.0
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Abilities
 */
class Test_Abilities extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Abilities::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_categories_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_category' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_abilities' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_category.
	 *
	 * @covers ::register_category
	 *
	 * @return void
	 */
	public function test_register_category(): void {
		$sibling = array( Event_Abilities::get_instance(), 'register_category' );

		wp_unregister_ability_category( Abilities::CATEGORY );

		// The event side answers the same action and would otherwise be the one
		// that registers the category, leaving this class nothing to do.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		remove_action( 'wp_abilities_api_categories_init', $sibling );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_categories_init' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		add_action( 'wp_abilities_api_categories_init', $sibling );

		$category = wp_get_ability_category( Abilities::CATEGORY );

		$this->assertNotNull( $category, 'Failed to assert that the GatherPress ability category is registered.' );
		$this->assertSame(
			'GatherPress',
			$category->get_label(),
			'Failed to assert the ability category label.'
		);
	}

	/**
	 * Coverage for register_category when the event side already registered it.
	 *
	 * @covers ::register_category
	 *
	 * @return void
	 */
	public function test_register_category_stands_down_when_already_registered(): void {
		wp_unregister_ability_category( Abilities::CATEGORY );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_categories_init' );

		// The sibling class listens on the same action, and a second pass runs
		// over a populated registry. Either would be a duplicate registration,
		// which the Abilities API reports as incorrect usage.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_categories_init' );

		$this->assertNotNull(
			wp_get_ability_category( Abilities::CATEGORY ),
			'Failed to assert that the category survives a second registration pass.'
		);
	}

	/**
	 * Coverage for register_abilities.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_register_abilities(): void {
		// The plugin already registered these while WordPress booted, so clear
		// them first: re-firing the action over a populated registry is a
		// duplicate registration, which the Abilities API rightly reports as
		// incorrect usage.
		wp_unregister_ability( 'gatherpress/get-upcoming-events' );
		wp_unregister_ability( 'gatherpress/get-rsvp-counts' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_init' );

		$counts = wp_get_ability( 'gatherpress/get-rsvp-counts' );

		$this->assertNotNull( $counts, 'Failed to assert that the RSVP counts ability is registered.' );

		$meta = $counts->get_meta();

		$this->assertTrue(
			$meta['show_in_rest'],
			'Failed to assert that the RSVP counts ability is exposed over REST.'
		);
		$this->assertTrue(
			$meta['annotations']['readonly'],
			'Failed to assert that the RSVP counts ability is annotated read-only.'
		);
		$this->assertSame(
			Abilities::CATEGORY,
			$counts->get_category(),
			'Failed to assert the ability category.'
		);
		$this->assertArrayHasKey(
			'post_id',
			$counts->get_input_schema()['properties'],
			'Failed to assert that the ability takes a post ID rather than an event ID.'
		);
	}

	/**
	 * Coverage for can_read_rsvps with input that names no post.
	 *
	 * @covers ::can_read_rsvps
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_can_read_rsvps_rejects_input_without_a_post(): void {
		$instance = Abilities::get_instance();

		$this->assertFalse(
			$instance->can_read_rsvps(),
			'Failed to assert that a null input is rejected.'
		);
		$this->assertFalse(
			$instance->can_read_rsvps( 'not-an-array' ),
			'Failed to assert that a non-array input is rejected.'
		);
		$this->assertFalse(
			$instance->can_read_rsvps( array() ),
			'Failed to assert that input without a post_id is rejected.'
		);
		$this->assertFalse(
			$instance->can_read_rsvps( array( 'post_id' => PHP_INT_MAX ) ),
			'Failed to assert that a non-existent post is rejected.'
		);
	}

	/**
	 * Coverage for can_read_rsvps against a post type that takes no RSVPs.
	 *
	 * @covers ::can_read_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_rsvps_rejects_a_post_type_without_rsvp_support(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse(
			$instance->can_read_rsvps( array( 'post_id' => $post->ID ) ),
			'Failed to assert that a post without RSVP support is rejected.'
		);
	}

	/**
	 * The gate follows RSVP support rather than event date support.
	 *
	 * @covers ::can_read_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_rsvps_allows_a_post_type_with_only_rsvp_support(): void {
		$instance  = Abilities::get_instance();
		$post_type = 'gp_rsvp_only';

		register_post_type(
			$post_type,
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
			)
		);

		$post = $this->mock->post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		)->get();

		wp_set_current_user( 0 );

		$readable = $instance->can_read_rsvps( array( 'post_id' => $post->ID ) );

		unregister_post_type( $post_type );

		$this->assertTrue(
			$readable,
			'Failed to assert that RSVP support alone is enough to read the counts.'
		);
	}

	/**
	 * Coverage for can_read_rsvps against a readable event.
	 *
	 * @covers ::can_read_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_rsvps_allows_a_readable_event(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		)->get();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue(
			$instance->can_read_rsvps( array( 'post_id' => $post->ID ) ),
			'Failed to assert that a published event is readable.'
		);
	}

	/**
	 * Coverage for can_read_rsvps against a password-protected event.
	 *
	 * @covers ::can_read_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_rsvps_rejects_a_password_protected_event(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'     => 'gatherpress_event',
				'post_status'   => 'publish',
				'post_password' => 'unit-test',
			)
		)->get();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse(
			$instance->can_read_rsvps( array( 'post_id' => $post->ID ) ),
			'Failed to assert that a password-protected event withholds its RSVP counts.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue(
			$instance->can_read_rsvps( array( 'post_id' => $post->ID ) ),
			'Failed to assert that whoever manages the event still reads its RSVP counts.'
		);
	}

	/**
	 * Coverage for get_rsvp_counts without a usable post.
	 *
	 * @covers ::get_rsvp_counts
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_get_rsvp_counts_returns_empty_without_a_post(): void {
		$instance = Abilities::get_instance();

		$this->assertSame(
			array(),
			$instance->get_rsvp_counts(),
			'Failed to assert that a null input yields no counts.'
		);
		$this->assertSame(
			array(),
			$instance->get_rsvp_counts( array( 'post_id' => 0 ) ),
			'Failed to assert that a zero post ID yields no counts.'
		);
	}

	/**
	 * Coverage for get_rsvp_counts against a real event.
	 *
	 * @covers ::get_rsvp_counts
	 *
	 * @return void
	 */
	public function test_get_rsvp_counts_counts_responses(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		)->get();

		$counts = $instance->get_rsvp_counts( array( 'post_id' => $post->ID ) );

		$this->assertArrayHasKey( 'all', $counts, 'Failed to assert that a total is present.' );
		$this->assertArrayHasKey( 'attending', $counts, 'Failed to assert that attending is counted.' );
		$this->assertIsInt( $counts['attending'], 'Failed to assert that counts are integers.' );
		$this->assertSame( 0, $counts['attending'], 'Failed to assert that a fresh event has no attendees.' );
	}
}
