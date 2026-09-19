<?php
/**
 * Derives a venue's Geodata standard meta (geo_*) from its own
 * gatherpress_* fields, on the venue's own save.
 *
 * @package GatherPress\Core\Venue
 * @since 0.36.0
 */

namespace GatherPress\Core\Venue;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use WP_Post;

/**
 * Class Geo_Sync.
 *
 * @since 0.36.0
 */
final class Geo_Sync {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor — wires hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for venue geo-meta derivation.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'wp_after_insert_post', array( $this, 'on_post_saved' ), 10, 2 );
	}

	/**
	 * Derives geo_* meta from a saved venue's own fields.
	 *
	 * @since 0.36.0
	 *
	 * @param int     $post_id Post ID that just saved.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function on_post_saved( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| ! post_type_supports( $post->post_type, Venue::SUPPORT )
		) {
			return;
		}

		$venue       = new Venue( $post_id );
		$information = $venue->get_information();

		update_post_meta( $post_id, 'geo_latitude', $information['latitude'] );
		update_post_meta( $post_id, 'geo_longitude', $information['longitude'] );
		update_post_meta( $post_id, 'geo_address', $information['address'] );
		update_post_meta( $post_id, 'geo_public', 'publish' === $post->post_status ? 1 : 0 );
	}
}
