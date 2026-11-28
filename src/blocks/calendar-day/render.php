<?php
/**
 * Render the Calendar Day block.
 *
 * @package GatherPress\Core
 * @since 0.37.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar_Day;

$gatherpress_calendar_day = new Calendar_Day( $block );

// The day cell escapes the markup it builds, and its inner blocks escape their own output.
echo $gatherpress_calendar_day->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
