<?php
/**
 * Class is responsible for instances of venues.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Venue.
 */
class Venue {

	const POST_TYPE = 'gp_venue';

	/**
	 * Event post object.
	 *
	 * @var array|\WP_Post|null
	 */
	protected $venue = null;

	/**
	 * Event constructor.
	 *
	 * @param int $post_id An event post ID.
	 */
	public function __construct( int $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return null;
		}

		$this->venue    = get_post( $post_id );

		return $this->venue;
	}

}
