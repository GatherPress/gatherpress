<?php
/**
 * The "Event_Date" class handles the functionality of the Event Date block,
 * ensuring proper rendering and behavior for event date display.
 *
 * This class is responsible for validating that the Event Date block is
 * connected to a valid event before rendering. If no valid event is found,
 * the block will not render on the frontend.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class responsible for managing the "Event Date" block and its functionality,
 * including validation and rendering.
 *
 * @since 0.33.0
 */
final class Event_Date {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 0.33.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/event-date';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.33.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.33.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		add_filter( $render_block_hook, array( $this, 'validate_event' ), 10, 2 );
	}

	/**
	 * Validate that the block is connected to a valid event.
	 *
	 * Checks if the block has a valid event ID (either from the current post
	 * or from a postId override). If no valid event is found, returns an empty
	 * string to prevent rendering on the frontend.
	 *
	 * @since 0.33.0
	 *
	 * @param string $block_content The original block content.
	 * @param array  $block         The block instance array, used to determine the event.
	 *
	 * @return string The block content if valid event, empty string otherwise.
	 */
	public function validate_event( string $block_content, array $block ): string {
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Validate that the post type supports event_date.
		// Only check publish status if not in preview mode.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ||
			( ! is_preview() && 'publish' !== get_post_status( $post_id ) )
		) {
			return '';
		}

		return $block_content;
	}
}
