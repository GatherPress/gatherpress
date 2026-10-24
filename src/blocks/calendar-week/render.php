<?php
/**
 * Render the Calendar Week block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar_Week;

// The week row escapes the markup it builds, and the day blocks escape their own output.
echo Calendar_Week::render( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
