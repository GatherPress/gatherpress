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

// The calendar escapes the markup it builds, and the inner blocks escape their own output.
echo Calendar::render( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
