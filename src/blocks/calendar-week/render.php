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

$gatherpress_calendar_week = new Calendar_Week( $block );

// The week row escapes the markup it builds, and the day blocks escape their own output.
echo $gatherpress_calendar_week->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
