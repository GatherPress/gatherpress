<?php
/**
 * The "Add to calendar" class manages the core-block-variation,
 * it mainly prepares the output of the block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Validate;
use GatherPress\Core\Traits\Singleton;
use WP_Block;

/**
 * Class responsible for managing the "Add to calendar" block,
 * which is a block-variation of 'core/buttons'.
 *
 * @since 1.0.0
 */
class Add_To_Calendar {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_block_bindings_sources' ) );
	}

	/**
	 * Registers the "gatherpress/add-to-calendar" source to the Block Bindings API.
	 *
	 * $source_name:       A unique name for your custom binding source in the form of namespace/slug.
	 * $source_properties: An array of properties to define your binding source:
	 *     label:              An internationalized text string to represent the binding source. Note: this is not currently shown anywhere in the UI.
	 *     get_value_callback: A PHP callable (function, closure, etc.) that is called when a block’s attribute matches the $source_name parameter.
	 *     uses_context:       (Optional) Extends the block instance with an array of contexts if needed for the callback.
	 *                         For example, if you need the current post ID, you’d set this to [ 'postId' ].
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_block_bindings_sources(): void {
		register_block_bindings_source(
			'gatherpress/add-to-calendar',
			array(
				'label'              => __( 'Add to calendar', 'gatherpress' ),
				'get_value_callback' => array( $this, 'get_block_binding_values' ),
				'uses_context'       => array( 'postId' ),
			)
		);
	}

	/**
	 * Handle returning the block binding value for the current post.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $source_args    An array of arguments passed via the metadata.bindings.$attribute.args property from the block.
	 * @param WP_Block $block_instance The current instance of the block the binding is connected to as a WP_Block object.
	 *
	 * @return string|null The block binding value or null if something went wrong.
	 */
	public function get_block_binding_values( array $source_args, WP_Block $block_instance ): ?string {

		// If no 'service' argument is set, bail early.
		if ( empty( $source_args['service'] ) ) {
			return null;
		}

		// Get the post id from context.
		$post_id = $block_instance->context['postId'];

		// Check, its an event.
		if ( ! Validate::event_post_id( $post_id ) ) {
			return null;
		}
		// If is a valid event,
		// return with the requested url for the add-to-calendar service.
		$event = new Event( $post_id );
		switch ( $source_args['service'] ) {
			case 'google':
				// TEMP. workaround until the successor of #831 is in place,
				// which will replace this code and
				// make $event->get_google_calendar_link() a public method.
				return $event->get_calendar_links()['google']['link'];
			case 'ical':
				// TEMP. workaround until the successor of #831 is in place,
				// which will replace this code and
				// make $event->get_ics_calendar_download() a public method.
				return $event->get_calendar_links()['ical']['download'];
			case 'outlook':
				// TEMP. workaround until the successor of #831 is in place,
				// which will replace this code and
				// make $event->get_ics_calendar_download() a public method.
				return $event->get_calendar_links()['outlook']['download'];
			case 'yahoo':
				// TEMP. workaround until the successor of #831 is in place,
				// which will replace this code and
				// make $event->get_yahoo_calendar_link() a public method.
				return $event->get_calendar_links()['yahoo']['link'];
		}

		return null;
	}
}
