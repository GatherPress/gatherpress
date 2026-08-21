<?php
/**
 * Render Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 0.27.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Setup;
use GatherPress\Core\Event;

$gatherpress_block_instance = Setup::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );
$gatherpress_event          = new Event( $gatherpress_post_id );
$gatherpress_display        = esc_html(
	$gatherpress_event->get_display_datetime(
		$attributes['displayType'] ?? '',
		$attributes['startDateFormat'] ?? '',
		$attributes['endDateFormat'] ?? '',
		$attributes['separator'] ?? '',
		$attributes['showTimezone'] ?? ''
	)
);

// Mirrors core/post-date's isLink attribute: link the datetime to the event.
if ( ! empty( $attributes['isLink'] ) ) {
	$gatherpress_display = sprintf(
		'<a href="%s">%s</a>',
		esc_url( get_permalink( $gatherpress_post_id ) ),
		$gatherpress_display
	);
}

// The reader's timezone is only knowable in the browser, so this emits an empty
// placeholder carrying the event's GMT datetimes and its own timezone for the
// view module to fill. It stays hidden for a reader already in the event's
// timezone, and for a reader without JavaScript, where an unconverted second
// copy of the same time would be worse than nothing.
$gatherpress_viewer_time_context = '';

if ( ! empty( $attributes['showViewerTime'] ) ) {
	// Mirrors get_display_datetime(): the local-time line covers the same parts
	// of the range the block itself displays, so the two cannot disagree.
	$gatherpress_display_type = $attributes['displayType'] ?? '';
	$gatherpress_show_start   = $gatherpress_display_type
		? in_array( $gatherpress_display_type, array( 'start', 'both' ), true )
		: true;
	$gatherpress_show_end     = $gatherpress_display_type
		? in_array( $gatherpress_display_type, array( 'end', 'both' ), true )
		: true;

	$gatherpress_datetime = $gatherpress_event->get_datetime();

	if ( $gatherpress_show_start && ! empty( $gatherpress_datetime['datetime_start_gmt'] ) ) {
		$gatherpress_viewer_time_context = wp_json_encode(
			array(
				'startGmt'      => $gatherpress_datetime['datetime_start_gmt'],
				'endGmt'        => $gatherpress_show_end ? ( $gatherpress_datetime['datetime_end_gmt'] ?? '' ) : '',
				'eventTimezone' => $gatherpress_datetime['timezone'] ?? '',
				// The view script is a module and cannot import `@wordpress/i18n`,
				// so the sentence it fills in is translated here instead.
				/* translators: 1: event start in the viewer's timezone, 2: event end in the viewer's timezone. */
				'rangeFormat'   => __( '%1$s to %2$s your time', 'gatherpress' ),
				/* translators: %s: event start in the viewer's timezone. */
				'singleFormat'  => __( '%s your time', 'gatherpress' ),
			),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
		);
	}
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php echo wp_kses( $gatherpress_display, array( 'a' => array( 'href' => true ) ) ); ?>
	<?php if ( $gatherpress_viewer_time_context ) : ?>
		<?php // The `hidden` attribute is the no-JS state; the binding drops it once the browser has a label to show. ?>
		<span class="gatherpress-event-date__viewer-time" data-wp-interactive="gatherpress" data-wp-context='<?php echo $gatherpress_viewer_time_context; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>' data-wp-text="state.viewerTimeLabel" data-wp-bind--hidden="!state.viewerTimeLabel" hidden></span>
	<?php endif; ?>
</div>
