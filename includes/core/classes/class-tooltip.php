<?php
/**
 * Tooltip class for managing tooltip format type functionality.
 *
 * This class provides utility methods and constants for the GatherPress tooltip
 * RichText format type, including allowed HTML attributes for safe rendering.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Tooltip.
 *
 * Provides tooltip-related utilities for the GatherPress plugin.
 *
 * @since 1.0.0
 */
class Tooltip {
	/**
	 * CSS class name for tooltip elements.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CSS_CLASS = 'gatherpress-tooltip';

	/**
	 * Data attribute for tooltip content.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DATA_ATTR = 'data-gatherpress-tooltip';

	/**
	 * Data attribute for tooltip text color.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DATA_ATTR_TEXT_COLOR = 'data-gatherpress-tooltip-text-color';

	/**
	 * Data attribute for tooltip background color.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DATA_ATTR_BG_COLOR = 'data-gatherpress-tooltip-bg-color';

	/**
	 * Get allowed HTML for tooltip format type.
	 *
	 * Returns an array of allowed HTML tags and attributes for use with wp_kses()
	 * when rendering content that may contain GatherPress tooltip markup.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of allowed HTML tags and their attributes.
	 */
	public static function get_allowed_html(): array {
		return array(
			'span' => array(
				'class'                    => true,
				self::DATA_ATTR            => true,
				self::DATA_ATTR_TEXT_COLOR => true,
				self::DATA_ATTR_BG_COLOR   => true,
				'style'                    => true,
			),
		);
	}
}
