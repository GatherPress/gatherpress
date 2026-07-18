<?php
/**
 * Render Venue block.
 *
 * The inner blocks are re-rendered with the resolved shadow-source post
 * (venue, tour, production, etc.) as their context — WordPress rendered
 * them with the surrounding post's context before this callback ran.
 * Emitting the wrapper here, after that, lets core's block-supports
 * pipeline (layout classes among the rest) decorate the one real wrapper.
 * When no source post resolves, the block renders nothing.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Venue;

$gatherpress_inner_content = Venue::get_instance()->render_inner_blocks( $block );

if ( null === $gatherpress_inner_content ) {
	return;
}

printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by core.
	$gatherpress_inner_content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks rendered by core.
);
