<?php
/**
 * Integration with the Yoast Duplicate Post plugin.
 *
 * Ensures GatherPress event data is properly duplicated when
 * using the Duplicate Post plugin to clone events.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Integrations\Duplicate_Post;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Setup.
 *
 * Handles integration with the Yoast Duplicate Post plugin.
 *
 * @since 1.0.0
 */
class Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for Duplicate Post integration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'duplicate_post_post_copy', array( $this, 'sync_duplicated_event' ) );
	}

	/**
	 * Sync the custom events table when an event is duplicated.
	 *
	 * When the Duplicate Post plugin clones an event, the post meta
	 * is copied but the custom gatherpress_events table is not. This method
	 * reads the cloned meta and writes it to the custom table.
	 *
	 * @since 1.0.0
	 *
	 * @param int $new_post_id The ID of the newly duplicated post.
	 * @return void
	 */
	public function sync_duplicated_event( int $new_post_id ): void {
		if ( Event::POST_TYPE !== get_post_type( $new_post_id ) ) {
			return;
		}

		$event    = new Event( $new_post_id );
		$datetime = $event->get_datetime();

		if ( empty( $datetime['datetime_start'] ) ) {
			return;
		}

		$event->save_datetimes(
			array(
				'post_id'        => $new_post_id,
				'datetime_start' => $datetime['datetime_start'],
				'datetime_end'   => $datetime['datetime_end'],
				'timezone'       => $datetime['timezone'],
			)
		);
	}
}
