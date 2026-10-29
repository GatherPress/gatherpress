<?php
/**
 * Render the Calendar block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar;

$gatherpress_calendar = new Calendar( $attributes, $block );

// The calendar escapes the markup it builds, and the inner blocks escape their own output.
echo $gatherpress_calendar->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
