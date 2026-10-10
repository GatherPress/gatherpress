<?php
/**
 * Post meta capabilities used across GatherPress.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Capability.
 *
 * Central definition for generic WordPress post meta capabilities.
 *
 * These meta capabilities are resolved per-post by WordPress map_meta_cap(),
 * mapping to the specific post type's registered capability strings based on
 * post status and ownership.
 *
 * @since TBD
 */
final class Capability {

	/**
	 * Meta capability for reading a specific post.
	 *
	 * A meta capability, so it is always paired with a post ID and resolves
	 * through WordPress map_meta_cap() to the right primitive capability for the
	 * post type and post status.
	 *
	 * @since TBD
	 * @var string
	 */
	const READ_POST = 'read_post';

	/**
	 * Meta capability for editing a specific post.
	 *
	 * A meta capability, so it is always paired with a post ID and resolves
	 * through WordPress map_meta_cap() to the post type's edit capability.
	 *
	 * @since TBD
	 * @var string
	 */
	const EDIT_POST = 'edit_post';

	/**
	 * Backward compatibility alias for READ_POST.
	 *
	 * @since TBD
	 * @var string
	 */
	const READ_CAPABILITY = self::READ_POST;

	/**
	 * Backward compatibility alias for EDIT_POST.
	 *
	 * @since TBD
	 * @var string
	 */
	const EDIT_CAPABILITY = self::EDIT_POST;
}
