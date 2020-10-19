<?php
/**
 * Class is responsible for loading all static assets.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets.
 */
class Assets {

	use Singleton;

	/**
	 * Path to `build` directory.
	 *
	 * @var string
	 */
	protected $build = GATHERPRESS_CORE_URL . 'assets/build/';

	/**
	 * Assets constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_enqueue_scripts' ) );
	}

	/**
	 * Enqueue frontend styles and scripts.
	 */
	public function enqueue_scripts() {
		$attendee = Attendee::get_instance();
		$event    = Event::get_instance();

//		$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/style.asset.php';
//		wp_enqueue_style( 'gatherpress-blocks-loader', $this->build . 'style.css', array(), $asset['version'] );
//
//		$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/style.asset.php';
//		wp_enqueue_style( 'gatherpress-style', $this->build . 'style.css', array(), $asset['version'] );

		$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/tailwind.asset.php';
		wp_enqueue_style( 'gatherpress-style', $this->build . 'tailwind.css', array(), $asset['version'] );

		if ( is_singular( 'gp_event' ) ) {
			global $post;

			$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/script.asset.php';
			wp_enqueue_script(
				'gatherpress-script',
				$this->build . 'script.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/event_single.asset.php';
			wp_enqueue_script(
				'gatherpress-event-single',
				$this->build . 'event_single.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			$user_id         = get_current_user_id();
			$attendee_status = $attendee->get_attendee( $post->ID, $user_id );

			wp_localize_script(
				'gatherpress-script',
				'GatherPress',
				array(
					'has_event_past'      => $event->has_event_past( $post->ID ),
					'event_rest_api'      => home_url( 'wp-json/gatherpress/v1/event' ),
					'nonce'               => wp_create_nonce( 'wp_rest' ),
					'post_id'             => $GLOBALS['post']->ID,
					'attendees'           => $attendee->get_attendees( $post->ID ),
					'current_user_status' => $attendee_status['status'] ?? '',
				)
			);
		}
	}

	/**
	 * Enqueue backend styles and scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'gatherpress-admin-css', $this->build . 'admin.css', array(), GATHERPRESS_THEME_VERSION );
	}

	/**
	 * Enqueue block styles and scripts.
	 */
	public function block_enqueue_scripts() {
		$post_id  = $GLOBALS['post']->ID;
		$event    = Event::get_instance();
		$attendee = Attendee::get_instance();

		$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/editor.asset.php';
		wp_enqueue_style( 'gatherpress-editor', $this->build . 'editor.css', array( 'wp-edit-blocks' ), $asset['version'] );

		$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/index.asset.php';
		wp_enqueue_script(
			'gatherpress-index',
			$this->build . 'index.js',
			array(
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-plugins',
				'wp-edit-post',
			),
			$asset['version'],
			true
		);

		wp_localize_script(
			'gatherpress-index',
			'GatherPress',
			array(
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'post_id'          => $post_id,
				'event_datetime'   => Event::get_instance()->get_datetime( $post_id ),
				'event_announced'  => ( get_post_meta( $post_id, 'gp-event-announce', true ) ) ? 1 : 0,
				'default_timezone' => sanitize_text_field( wp_timezone_string() ),
				'has_event_past'      => $event->has_event_past( $post_id ),
				'event_rest_api'      => home_url( 'wp-json/gatherpress/v1/event' ),
				'attendees'           => $attendee->get_attendees( $post_id ),
				'current_user_status' => $attendee_status['status'] ?? '',
			)
		);

		$asset = require_once GATHERPRESS_CORE_PATH . '/assets/build/tailwind.asset.php';
		wp_enqueue_style( 'gatherpress-style', $this->build . 'tailwind.css', array(), $asset['version'] );
	}

}
