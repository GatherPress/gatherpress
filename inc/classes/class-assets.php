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
	 * URL to `build` directory.
	 *
	 * @var string
	 */
	protected $build = GATHERPRESS_CORE_URL . 'assets/build/';

	/**
	 * Path to `build` directory.
	 *
	 * @var string
	 */
	protected $path = GATHERPRESS_CORE_PATH . '/assets/build/';

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
		$asset = require_once $this->path . 'style.asset.php';
		wp_enqueue_style( 'gatherpress-style', $this->build . 'style.css', array(), $asset['version'] );

		$asset = require_once $this->path . 'script.asset.php';
		wp_enqueue_script(
			'gatherpress-script',
			$this->build . 'script.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( is_singular( 'gp_event' ) ) {
			global $post;

			wp_localize_script(
				'gatherpress-script',
				'GatherPress',
				$this->localize( $post->ID ?? 0 )
			);
		}
	}

	/**
	 * Enqueue backend styles and scripts.
	 */
	public function admin_enqueue_scripts() {
		$asset = require_once $this->path . 'style.asset.php';
		wp_enqueue_style( 'gatherpress-style', $this->build . 'style.css', array(), $asset['version'] );

		$asset = require_once $this->path . 'admin.asset.php';
		wp_enqueue_style( 'gatherpress-admin', $this->build . 'admin.css', array(), $asset['version'] );
	}

	/**
	 * Enqueue block styles and scripts.
	 */
	public function block_enqueue_scripts() {
		$post_id = $GLOBALS['post']->ID ?? 0;
		$event   = new Event( $post_id );

		$asset = require_once $this->path . 'editor.asset.php';
		wp_enqueue_style( 'gatherpress-editor', $this->build . 'editor.css', array( 'wp-edit-blocks' ), $asset['version'] );

		$asset = require_once $this->path . 'index.asset.php';
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
			array_merge(
				$this->localize( $post_id ),
				array(
					'event_datetime'   => $event->get_datetime(),
					'event_announced'  => ( get_post_meta( $post_id, 'gp-event-announce', true ) ) ? 1 : 0,
					'default_timezone' => sanitize_text_field( wp_timezone_string() ),
				)
			)
		);
	}

	/**
	 * Localize data to JavaScript.
	 *
	 * @param int $post_id Post ID for an event.
	 *
	 * @return array
	 */
	protected function localize( int $post_id ) : array {
		$event = new Event( $post_id );

		return array(
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'post_id'             => $post_id,
			'has_event_past'      => $event->has_event_past(),
			'event_rest_api'      => home_url( 'wp-json/gatherpress/v1/event' ),
			'current_user_status' => $event->attendee->get_attendee( get_current_user_id() ) ?? '',
			'attendees'           => $event->attendee->get_attendees(),
		);
	}

}
