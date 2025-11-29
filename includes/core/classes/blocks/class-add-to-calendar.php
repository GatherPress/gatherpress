<?php
/**
 * The "Add_To_Calendar" class handles the functionality of the Add to Calendar block,
 * ensuring proper rendering and behavior for calendar integration.
 *
 * This class is responsible for transforming block content to replace calendar
 * placeholder hrefs with fully-generated calendar URLs based on event metadata.
 * It enables users to add events to services like Google, iCal, Outlook, and Yahoo.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Add_To_Calendar" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 1.0.0
 */
class Add_To_Calendar {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/add-to-calendar';

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
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		add_filter( $render_block_hook, array( $this, 'replace_calendar_placeholders' ), 10, 2 );
	}

	/**
	 * Replace placeholder calendar hrefs with generated event URLs.
	 *
	 * Scans the block content for known calendar link placeholders (e.g.,
	 * #gatherpress-google-calendar) and replaces them with fully-formed
	 * URLs based on the associated event data. This ensures that "Add to Calendar"
	 * links point to the correct service with event details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block content.
	 * @param array  $block         The block instance array, used to determine the event.
	 *
	 * @return string The modified block content with calendar hrefs replaced.
	 */
	public function replace_calendar_placeholders( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Validate that the post ID is an actual published event post type.
		if (
			Event::POST_TYPE !== get_post_type( $post_id ) ||
			'publish' !== get_post_status( $post_id )
		) {
			return '';
		}

		$event          = new Event( $post_id );
		$tag            = new WP_HTML_Tag_Processor( $block_content );
		$calendar_links = $event->get_calendar_links();
		$replacements   = array(
			'#gatherpress-google-calendar'  => $calendar_links['google']['link'] ?? '',
			'#gatherpress-ical-calendar'    => $calendar_links['ical']['link'] ?? '',
			'#gatherpress-outlook-calendar' => $calendar_links['outlook']['link'] ?? '',
			'#gatherpress-yahoo-calendar'   => $calendar_links['yahoo']['link'] ?? '',
		);

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$href = $tag->get_attribute( 'href' );

			if ( isset( $replacements[ $href ] ) && $replacements[ $href ] ) {
				$tag->set_attribute( 'href', $replacements[ $href ] );
			}
		}

		return $tag->get_updated_html();
	}
}
