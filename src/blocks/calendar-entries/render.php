<?php
/**
 * Render the Calendar Entries block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Calendar_Entries;

// The entries escape the markup they build, and the entry templates escape their own output.
echo Calendar_Entries::render( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
