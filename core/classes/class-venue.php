<?php
/**
 * Class is responsible for instances of venues.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Venue.
 */
class Venue {

	use Singleton;

	const POST_TYPE = 'gp_venue';
	const TAXONOMY  = '_gp_venue';

	/**
	 * Venue constructor.
	 */
	public function __construct() {
	}

	protected function setup_hooks(): void {

	}

}
