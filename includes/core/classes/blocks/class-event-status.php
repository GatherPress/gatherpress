<?php
/**
 * The "Event_Status" class handles the functionality of the Event Status block,
 * ensuring proper rendering and behavior for event status display.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;

/**
 * Class responsible for managing the "Event Status" block and its functionality,
 * including validation and rendering.
 *
 * @since 0.36.0
 */
final class Event_Status {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/event-status';

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
	 * @since 0.36.0
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
	 * @since 0.36.0
	 *
	 * @param string               $block_content The original block content.
	 * @param array<string, mixed> $block         The block instance array, used to determine the event.
	 *
	 * @return string The block content if valid event, empty string otherwise.
	 */
	public function validate_event( string $block_content, array $block ): string {
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Validate that the post type supports event_date.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ||
			! Event::is_viewable( $post_id )
		) {
			return '';
		}

		return $block_content;
	}
}
