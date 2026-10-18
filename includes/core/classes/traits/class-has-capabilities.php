<?php
/**
 * The Has_Capabilities trait gives a class the capabilities checked against its posts.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Capability;

/**
 * Has_Capabilities Trait.
 *
 * Gives a class the meta capabilities checked against its posts, defaulting to
 * the shared ones on `Capability`. A class that needs its own declares the
 * method itself, and a method declared in the class wins over the trait's.
 *
 * @since TBD
 */
trait Has_Capabilities {

	/**
	 * Get the capability for reading one of this class's posts.
	 *
	 * Defaults to `Capability::READ_POST`, a meta capability that WordPress
	 * resolves against the post's own type. A class overrides this method to
	 * use its own capability.
	 *
	 * @since TBD
	 *
	 * @return string The capability, checked together with a post ID.
	 */
	public static function read_capability(): string {
		return Capability::READ_POST;
	}

	/**
	 * Get the capability for editing one of this class's posts.
	 *
	 * Defaults to `Capability::EDIT_POST`, a meta capability that WordPress
	 * resolves against the post's own type. A class overrides this method to
	 * use its own capability.
	 *
	 * @since TBD
	 *
	 * @return string The capability, checked together with a post ID.
	 */
	public static function edit_capability(): string {
		return Capability::EDIT_POST;
	}
}
