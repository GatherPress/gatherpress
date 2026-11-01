<?php
/**
 * Event Date Block.
 *
 * @package GatherPress\Core
 * @since 0.28.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Event_Date\Html_Renderer;

$gatherpress_event_date = new Html_Renderer( $attributes, $block );

// The renderer escapes the markup it builds.
echo $gatherpress_event_date->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
