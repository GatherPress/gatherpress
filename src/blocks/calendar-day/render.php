<?php
/**
 * Render the Calendar Day block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar_Day;

// The day cell escapes the markup it builds, and its inner blocks escape their own output.
echo Calendar_Day::render( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
