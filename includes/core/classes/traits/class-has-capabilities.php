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
use WP_Post;
use WP_REST_Request;

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

	/**
	 * Whether a post password keeps one of this class's posts from the current viewer.
	 *
	 * The viewer still needs the password and cannot edit the post. A REST
	 * request that passes the right `password` argument counts as having it.
	 *
	 * @since 0.36.1
	 *
	 * @param WP_Post              $post    The post.
	 * @param WP_REST_Request|null $request Optional. The REST request, for its `password` argument.
	 * @phpstan-param WP_REST_Request<array<string, mixed>>|null $request
	 *
	 * @return bool True when the post's details stay hidden.
	 */
	public static function is_hidden_by_password( WP_Post $post, ?WP_REST_Request $request = null ): bool {
		return post_password_required( $post )
			&& ! current_user_can( self::edit_capability(), $post->ID )
			&& (
				null === $request
				|| ! hash_equals( $post->post_password, (string) $request->get_param( 'password' ) )
			);
	}
}
