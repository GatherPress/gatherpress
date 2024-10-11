<?php
/**
 * The "Add to calendar" class manages the core-block-variation,
 * registers and enqueues assets and prepares the output of the block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Block_Variation;
use WP_Block;

/**
 * Class responsible for managing the "Add to calendar" block,
 * which is a block-variation of 'core/buttons'.
 *
 * @since 1.0.0
 */
class Add_To_Calendar {
	/**
	 * Common class that handles registering and enqueuing of assets.
	 */
	use Block_Variation;

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

		// Load JS and CSS from the "/build" directory.
		$this->register_and_enqueue_assets();

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
				'get_value_callback' => array( $this, 'get_block_binding_values' ) ),
				'uses_context'       => array( 'postId' ),
			)
		);
	}

	/**
	 * Handle returing the block binding value for the current post type.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $source_args    An array of arguments passed via the metadata.bindings.$attribute.args property from the block.
	 * @param WP_Block $block_instance The current instance of the block the binding is connected to as a WP_Block object.
	 * @param mixed    $attribute_name The current attribute set via the metadata.bindings.$attribute property on the block.
	 *
	 * @return string|null The block binding value or null if something went wrong.
	 */
	public function get_block_binding_values( array $source_args, WP_Block $block_instance ): ?string {

		// If no 'label' or 'support' argument is set, bail early with the current post type.
		if ( ! isset( $source_args['service'] ) ) {
			return null;
		}

		// Get the post type from context.
		$post_id = $block_instance->context['postId'];
		$event   = new Event( $post_id );

		// If 'service' argument is set, return with the requested url for the add-to-calendar service.
		if ( ! empty( $source_args['service'] ) && $event ) {
			switch ( $source_args['service'] ) {
				case 'google':
					// return $event->get_google_calendar_link(); // protected method !
					return $event->get_calendar_links()['google']['link']; // TEMP. workaround !
				case 'ical':
					// return $event->get_ics_calendar_download(); // protected method !
					return $event->get_calendar_links()['ical']['download']; // TEMP. workaround !
				case 'outlook':
					// return $event->get_ics_calendar_download(); // protected method !
					return $event->get_calendar_links()['outlook']['download']; // TEMP. workaround !
				case 'yahoo':
					// return $event->get_yahoo_calendar_link(); // protected method !
					return $event->get_calendar_links()['yahoo']['link']; // TEMP. workaround !
			}
		}

		return null;
	}
}
