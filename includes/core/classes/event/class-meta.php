<?php
/**
 * Owns the event post-meta surface.
 *
 * Registers the read-only datetime / timezone meta on any post type that
 * declares `gatherpress-event-date` support, plus the always-on
 * RSVP / attendance / online-event-link meta on the built-in event post
 * type. Also owns the REST readonly-strip filter that pairs with the
 * `__return_false` auth callbacks.
 *
 * Sibling singleton to `Event\Setup` — `Setup` keeps post-type
 * registration, .ics rewrite plumbing, and date-formatting filters;
 * `Meta` keeps everything that touches `register_post_meta()`.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use stdClass;
use WP_REST_Request;

/**
 * Class Meta.
 *
 * Singleton owning event post-meta registration. Hooks
 * `registered_post_type` so any post type that declares
 * `gatherpress-event-date` — including companion-plugin types — picks
 * up the same meta shape, and the built-in event post type also picks
 * up the always-on RSVP / attendance / online-event-link meta on the
 * same hook.
 *
 * @since 1.0.0
 */
class Meta {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for event meta registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'register' ) );
	}

	/**
	 * Registers event meta for any post type that declares the
	 * relevant supports. Two flavors:
	 *
	 * - `gatherpress-event-date` support (any registered post type) gets
	 *   the datetime / timezone read-only meta + the REST readonly-strip
	 *   filter.
	 * - The built-in `Event::POST_TYPE` additionally gets the always-on
	 *   RSVP / attendance / online-event-link meta — these aren't gated
	 *   on a `post_type_supports()` flag because they're identity-bound
	 *   to the canonical event post type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type that was just registered.
	 * @return void
	 */
	public function register( string $post_type ): void {
		if ( post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			$this->register_event_date_meta( $post_type );
		}

		if ( Event::POST_TYPE === $post_type ) {
			$this->register_event_only_meta();
		}
	}

	/**
	 * Registers datetime meta + the read-only REST filter for a post
	 * type that declares `gatherpress-event-date` support.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type to register against.
	 * @return void
	 */
	protected function register_event_date_meta( string $post_type ): void {
		// `WP_REST_Posts_Controller` only attaches the `meta` field to a post
		// type's REST schema when the post type declares `custom-fields`
		// support — without it, `register_post_meta()` quietly registers the
		// keys but the editor's autosave / publish PUT silently strips them
		// and the Event Date block renders the em-dash placeholder on the
		// frontend. Force the support on so companion plugins don't have to
		// know about this WordPress requirement.
		add_post_type_support( $post_type, 'custom-fields' );

		$event_date_meta = array(
			'gatherpress_datetime'           => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_start'     => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
			),
			'gatherpress_datetime_start_gmt' => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_end'       => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_end_gmt'   => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_timezone'           => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
		);

		foreach ( $event_date_meta as $meta_key => $args ) {
			register_post_meta( $post_type, $meta_key, $args );
		}

		// Filter read-only datetime meta from REST requests for this post type.
		add_filter(
			sprintf( 'rest_pre_insert_%s', $post_type ),
			array( $this, 'filter_readonly_meta' ),
			10,
			2
		);
	}

	/**
	 * Registers meta that only lives on the built-in event post type (RSVP
	 * toggles, guest / attendance limits, online event link).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_event_only_meta(): void {
		// Always register gatherpress_enable_rsvp so it can be written in all modes.
		// Missing meta is treated as "on"; only an explicit 0 disables RSVP per event.
		$event_only_meta = array(
			'gatherpress_enable_rsvp'           => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 1,
			),
			'gatherpress_max_guest_limit'       => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => (int) Settings::get_instance()->get( 'max_guest_limit' ),
			),
			'gatherpress_enable_anonymous_rsvp' => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => (bool) Settings::get_instance()->get( 'enable_anonymous_rsvp' ),
			),
			// Always register so it can be written regardless of open RSVP mode.
			// Stored as integer (1 = enabled, 0 = disabled); an unset meta (empty string) is treated as enabled.
			'gatherpress_enable_open_rsvp'      => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 1,
			),
			'gatherpress_online_event_link'     => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_url',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			),
			'gatherpress_max_attendance_limit'  => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => (int) Settings::get_instance()->get( 'max_attendance_limit' ),
			),
		);

		foreach ( $event_only_meta as $meta_key => $args ) {
			register_post_meta( Event::POST_TYPE, $meta_key, $args );
		}
	}

	/**
	 * Filter out read-only meta fields from REST API requests.
	 *
	 * This prevents the "Publishing failed. Sorry, you are not allowed to edit
	 * the gatherpress_datetime_start custom field." error that occurs when the
	 * block editor tries to save derived meta fields that have auth_callback
	 * set to __return_false.
	 *
	 * The derived datetime fields are populated programmatically via the
	 * `Event\Setup::set_datetimes()` method when gatherpress_datetime is saved,
	 * so any values sent via REST API should be silently discarded.
	 *
	 * @since 1.0.0
	 *
	 * @param stdClass        $prepared_post An object representing a single post prepared for inserting or updating.
	 * @param WP_REST_Request $request       Request object.
	 * @return stdClass The prepared post object.
	 */
	public function filter_readonly_meta( stdClass $prepared_post, WP_REST_Request $request ): stdClass {
		$readonly_keys = array(
			'gatherpress_datetime_start',
			'gatherpress_datetime_start_gmt',
			'gatherpress_datetime_end',
			'gatherpress_datetime_end_gmt',
			'gatherpress_timezone',
		);

		$meta = $request->get_param( 'meta' );

		if ( is_array( $meta ) ) {
			foreach ( $readonly_keys as $key ) {
				unset( $meta[ $key ] );
			}

			$request->set_param( 'meta', $meta );
		}

		return $prepared_post;
	}
}
