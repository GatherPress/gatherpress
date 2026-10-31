<?php
/**
 * Event Date Block Template.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $wrapper_attributes, $datetime, $link, $has_viewer_time ) ) {
	return;
}

$gatherpress_tooltip = ' data-wp-class--gatherpress-tooltip="state.hasViewerTime"'
	. ' data-wp-bind--data-gatherpress-tooltip="state.viewerTimeLabel"';
?>
<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<?php if ( '' !== $link ) { ?>
		<a href="<?php echo esc_url( $link ); ?>"<?php echo $has_viewer_time ? $gatherpress_tooltip : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php } elseif ( $has_viewer_time ) { ?>
		<?php // A link is focusable already: the plain datetime needs a tab stop for keyboard users to reach the tooltip. ?>
		<span<?php echo $gatherpress_tooltip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-bind--tabindex="state.viewerTimeTabIndex">
	<?php } ?>
	<?php echo wp_kses( $datetime, array( 'time' => array( 'datetime' => true ) ) ); ?>
	<?php if ( $has_viewer_time ) { ?>
		<span class="screen-reader-text gatherpress--screen-reader-text gatherpress-tooltip-notice" data-wp-text="state.viewerTimeSrLabel"></span>
	<?php } ?>
	<?php if ( '' !== $link ) { ?>
		</a>
	<?php } elseif ( $has_viewer_time ) { ?>
		</span>
	<?php } ?>
</div>
