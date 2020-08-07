<?php

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	use Singleton;

	protected $_build = GATHERPRESS_CORE_URL . '/assets/build/';

	/**
	 * Assets constructor.
	 */
	protected function __construct() {

		$this->_setup_hooks();

	}

	/**
	 * Setup hooks.
	 */
	protected function _setup_hooks() : void {

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'block_enqueue_scripts' ] );

	}

	/**
	 * Enqueue frontend styles and scripts.
	 */
	public function enqueue_scripts() : void {

		$attendee = Attendee::get_instance();
		$event    = Event::get_instance();

		$asset = require_once( GATHERPRESS_CORE_PATH . '/assets/build/style.asset.php' );
		wp_enqueue_style( 'gatherpress-style',  $this->_build . 'style.css', [], $asset['version'] );

		if ( is_singular( 'gp_event' ) ) {
			global $post;

			$asset = require_once( GATHERPRESS_CORE_PATH . '/assets/build/event_single.asset.php' );
			wp_enqueue_script(
				'gatherpress-event-single',
				$this->_build . 'event_single.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			$user_id         = get_current_user_id();
			$attendee_status = $attendee->get_attendee( $post->ID, $user_id );

			wp_localize_script(
				'gatherpress-event-single',
				'GatherPress',
				[
					'has_event_past'      => $event->has_event_past( $post->ID ),
					'event_rest_api'      => home_url( 'wp-json/gatherpress/v1/event/' ),
					'nonce'               => wp_create_nonce( 'wp_rest' ),
					'post_id'             => $GLOBALS['post']->ID,
					'attendees'           => $attendee->get_attendees( $post->ID ),
					'current_user_status' => $attendee_status['status'] ?? '',
				]
			);
		}

	}

	/**
	 * Enqueue backend styles and scripts.
	 */
	public function admin_enqueue_scripts() : void {

		wp_enqueue_style( 'gatherpress-admin-css', $this->_build . 'admin.css', [], GATHERPRESS_THEME_VERSION );

	}

	/**
	 * Enqueue block styles and scripts.
	 */
	public function block_enqueue_scripts() : void {

		$post_id = $GLOBALS['post']->ID;

		$asset = require_once( GATHERPRESS_CORE_PATH . '/assets/build/editor.asset.php' );
		wp_enqueue_style( 'gatherpress-editor', $this->_build . 'editor.css', [ 'wp-edit-blocks' ], $asset['version'] );

		$asset = require_once( GATHERPRESS_CORE_PATH . '/assets/build/index.asset.php' );
		wp_enqueue_script(
			'gatherpress-index',
			$this->_build . 'index.js',
			[
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-plugins',
				'wp-edit-post',
			],
			$asset['version']
		);

		wp_localize_script(
			'gatherpress-index',
			'GatherPress',
			[
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'post_id'          => $post_id,
				'event_datetime'   => Event::get_instance()->get_datetime( $post_id ),
				'event_announced'  => ( get_post_meta( $post_id, 'gp-event-announce', true ) ) ? 1 : 0,
				'default_timezone' => sanitize_text_field( wp_timezone_string() ),
			]
		);

	}

}

// EOF
