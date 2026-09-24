<?php
/**
 * Answers reads of renamed post meta with the value saved under the old name.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Renamed_Meta.
 *
 * Several meta keys were renamed in 0.36.0. Filtering the read rather than
 * patching each caller means PHP, the REST API and the editor all see the
 * saved value without any of them knowing there was a rename. That matters
 * for the editor in particular, which cannot tell an unset meta from one set
 * to its registered default, because WordPress answers both with the default.
 *
 * GatherPress Alpha rewrites the rows outright. This covers everyone who is
 * not running it, and the whole class goes away in 0.37.0 along with the old
 * registrations.
 *
 * @since TBD
 */
final class Renamed_Meta {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Post meta renamed in 0.36.0, mapped current name to former name.
	 *
	 * @since TBD
	 * @var array<string, string>
	 */
	const RENAMED = array(
		'gatherpress_capacity'    => 'gatherpress_max_attendance_limit',
		'gatherpress_guest_limit' => 'gatherpress_max_guest_limit',
	);

	/**
	 * Keys currently being resolved, so the filter does not re-enter itself.
	 *
	 * @since TBD
	 * @var array<string, bool>
	 */
	private array $resolving = array();

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'get_post_metadata', array( $this, 'answer_with_former_name' ), 10, 4 );
	}

	/**
	 * Answer a read of a renamed key with the value saved under its former name.
	 *
	 * Presence is tested rather than the value, because a registered default
	 * makes an unset key indistinguishable from one deliberately set to that
	 * default, and because 0 is a real answer for both of the limits here.
	 *
	 * @since TBD
	 *
	 * @param mixed  $value     What `get_metadata()` has so far, null when nothing has answered.
	 * @param int    $object_id The post ID being read.
	 * @param string $meta_key  The meta key being read.
	 * @param bool   $single    Whether a single value was asked for.
	 *
	 * @return mixed The former value when there is one to give, otherwise $value untouched.
	 */
	public function answer_with_former_name( $value, int $object_id, string $meta_key, bool $single ) {
		// The re-entry guard matters: metadata_exists() runs this same filter,
		// so testing the current key from inside it would recurse forever.
		if ( ! isset( self::RENAMED[ $meta_key ] ) || ! empty( $this->resolving[ $meta_key ] ) ) {
			return $value;
		}

		$former = self::RENAMED[ $meta_key ];

		$this->resolving[ $meta_key ] = true;
		$has_current                  = metadata_exists( 'post', $object_id, $meta_key );
		$this->resolving[ $meta_key ] = false;

		if ( $has_current || ! metadata_exists( 'post', $object_id, $former ) ) {
			return $value;
		}

		$saved = get_post_meta( $object_id, $former, true );

		return $single ? $saved : array( $saved );
	}
}
