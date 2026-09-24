<?php
/**
 * Holds the 0.36.0 key renames and answers reads with the former name.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Renamed_Keys.
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
final class Renamed_Keys {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Settings renamed in 0.36.0, mapped current name to former name.
	 *
	 * @since TBD
	 * @var array<string, string>
	 */
	const OPTIONS = array(
		'capacity'                    => 'max_attendance_limit',
		'guest_limit'                 => 'max_guest_limit',
		'enable_rsvp_cleanup'         => 'rsvp_cleanup_switch',
		'rsvp_cleanup_multiplier'     => 'rsvp_cleanup_interval',
		'use_event_date_for_publish'  => 'post_or_event_date',
		'custom_map_tile_url'         => 'map_tile_url_custom',
		'custom_map_tile_attribution' => 'map_tile_attribution_custom',
		'venue_map_type'              => 'venue_map_default_type',
		'venue_map_render_mode'       => 'venue_map_default_render_mode',
		'venue_map_zoom'              => 'venue_map_default_zoom',
		'venue_map_height'            => 'venue_map_default_height',
		'venue_map_aspect_ratio'      => 'venue_map_default_aspect_ratio',
		'venue_map_scale'             => 'venue_map_default_scale',
	);

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
	 * The names a setting answers to, current first.
	 *
	 * A setting renamed in 0.36.0 also answers to the name it had before, so
	 * a site that has not re-saved its settings still resolves the value and
	 * a network that opted the old name into inheritance keeps inheriting it.
	 * The former name drops out of this list in 0.37.0.
	 *
	 * @since TBD
	 *
	 * @param string $option The option key being resolved.
	 *
	 * @return string[] One name, or two when the setting was renamed.
	 */
	public static function option_names( string $option ): array {
		return array_filter( array( $option, self::OPTIONS[ $option ] ?? '' ) );
	}

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
