<?php
/**
 * Exposes GatherPress RSVP tallies through the WordPress Abilities API.
 *
 * Registers a read-only ability so clients that speak the Abilities API — the
 * REST endpoints under `wp-abilities/v1`, WP-CLI, and AI tooling — can ask how
 * many people have replied without hard-coding GatherPress's comment storage.
 *
 * RSVPs are a post type support of their own, so the ability answers for any
 * post type that declares `gatherpress-rsvp`, event or not.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Abilities.
 *
 * Registers the RSVP abilities with the WordPress Abilities API.
 *
 * @since 0.36.0
 */
final class Abilities {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Slug of the ability category GatherPress registers its abilities under.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const CATEGORY = 'gatherpress';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the GatherPress ability category.
	 *
	 * The category is shared with the event abilities, and either subsystem can
	 * be the one that boots first, so both register it and whichever runs
	 * second stands down rather than registering it twice.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function register_category(): void {
		if ( wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'GatherPress', 'gatherpress' ),
				'description' => __( 'Read event schedules and RSVP tallies managed by GatherPress.', 'gatherpress' ),
			)
		);
	}

	/**
	 * Register the RSVP abilities.
	 *
	 * The ability is read-only and idempotent, so it is safe for a client to
	 * call speculatively.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		wp_register_ability(
			'gatherpress/get-rsvp-counts',
			array(
				'label'               => __( 'Get RSVP counts', 'gatherpress' ),
				'description'         => __(
					'Count the responses to one post that takes RSVPs, broken down by status.',
					'gatherpress'
				),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The post to count responses for.', 'gatherpress' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
				),
				'execute_callback'    => array( $this, 'get_rsvp_counts' ),
				'permission_callback' => array( $this, 'can_read_rsvps' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Whether the current user may read the requested post's RSVP responses.
	 *
	 * Defers to the rule the roster itself follows, which rejects any post type
	 * without `gatherpress-rsvp` support and keeps a published post's responses
	 * behind its password gate.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return bool True when the post takes RSVPs and its responses are readable.
	 */
	public function can_read_rsvps( $input = null ): bool {
		$post_id = $this->get_post_id( $input );

		return $post_id ? Event::can_read_rsvps( $post_id ) : false;
	}

	/**
	 * Count the responses to one post by RSVP status.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return int[] Response counts keyed by status, including an `all` total.
	 */
	public function get_rsvp_counts( $input = null ): array {
		$post_id = $this->get_post_id( $input );
		$counts  = array();

		// The permission callback already rejected anything without a roster,
		// but the ability is a public entry point, so do not build an RSVP
		// object around a post that does not exist.
		if ( ! $post_id ) {
			return $counts;
		}

		foreach ( ( new Rsvp( $post_id ) )->responses() as $status => $response ) {
			$counts[ $status ] = (int) $response['count'];
		}

		return $counts;
	}

	/**
	 * Read the post ID out of the ability input.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return int The requested post ID, or 0 when the input names none.
	 */
	private function get_post_id( $input ): int {
		return is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0;
	}
}
