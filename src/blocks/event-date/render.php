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
use GatherPress\Core\Settings;

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

$gatherpress_settings           = Settings::get_instance();
$gatherpress_show_timezone_attr = $attributes['showTimezone'] ?? '';
$gatherpress_is_timezone_active = '' !== $gatherpress_show_timezone_attr
	? 'yes' === $gatherpress_show_timezone_attr
	: (bool) $gatherpress_settings->get( 'show_timezone' );

// The viewer's timezone is only knowable in the browser, so this emits a context
// payload carrying the event's GMT datetimes and its own timezone for the view
// module to derive the viewer's local time in a tooltip.
$gatherpress_viewer_time_context = '';

if (
	! empty( $attributes['showViewerTime'] )
	&& $gatherpress_is_timezone_active
	&& $gatherpress_settings->get( 'show_viewer_timezone' )
) {
	// Mirrors get_display_datetime(): the local-time tooltip covers the same parts
	// of the range the block itself displays, so the two cannot disagree. An
	// empty display type reads as both there, and edit.js normalizes it the
	// same way, so the editor preview and this agree on that input too.
	$gatherpress_display_type = ! empty( $attributes['displayType'] )
		? $attributes['displayType']
		: 'both';
	$gatherpress_show_start   = in_array( $gatherpress_display_type, array( 'start', 'both' ), true );
	$gatherpress_show_end     = in_array( $gatherpress_display_type, array( 'end', 'both' ), true );

	$gatherpress_datetime  = $gatherpress_event->get_datetime();
	$gatherpress_start_gmt = $gatherpress_show_start ? ( $gatherpress_datetime['datetime_start_gmt'] ?? '' ) : '';
	$gatherpress_end_gmt   = $gatherpress_show_end ? ( $gatherpress_datetime['datetime_end_gmt'] ?? '' ) : '';

	// An end-only block converts its end: that is the only time it displays, so
	// it is the one the viewer needs converting, and get_display_datetime()
	// shows the end alone there rather than showing nothing.
	if ( $gatherpress_start_gmt || $gatherpress_end_gmt ) {
		$gatherpress_viewer_time_context = wp_json_encode(
			array(
				'startGmt'      => $gatherpress_start_gmt,
				'endGmt'        => $gatherpress_end_gmt,
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

$gatherpress_wrapper_attrs = get_block_wrapper_attributes();
if ( $gatherpress_viewer_time_context ) {
	$gatherpress_wrapper_attrs .= sprintf(
		' data-wp-interactive="gatherpress" data-wp-context=\'%s\'',
		$gatherpress_viewer_time_context
	);
}
?>
<div <?php echo wp_kses_data( $gatherpress_wrapper_attrs ); ?>>
	<?php if ( ! empty( $attributes['isLink'] ) ) : ?>
		<?php if ( $gatherpress_viewer_time_context ) : ?>
			<a
				href="<?php echo esc_url( get_permalink( $gatherpress_post_id ) ); ?>"
				data-wp-class--gatherpress-tooltip="state.hasViewerTime"
				data-wp-bind--data-gatherpress-tooltip="state.viewerTimeLabel"
			>
				<?php echo esc_html( $gatherpress_display ); ?>
				<span class="screen-reader-text" data-wp-text="state.viewerTimeSrLabel"></span>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( get_permalink( $gatherpress_post_id ) ); ?>"><?php echo esc_html( $gatherpress_display ); ?></a>
		<?php endif; ?>
	<?php elseif ( $gatherpress_viewer_time_context ) : ?>
		<span
			data-wp-class--gatherpress-tooltip="state.hasViewerTime"
			data-wp-bind--data-gatherpress-tooltip="state.viewerTimeLabel"
			data-wp-bind--tabindex="state.viewerTimeTabIndex"
		>
			<?php echo esc_html( $gatherpress_display ); ?>
			<span class="screen-reader-text" data-wp-text="state.viewerTimeSrLabel"></span>
		</span>
	<?php else : ?>
		<?php echo esc_html( $gatherpress_display ); ?>
	<?php endif; ?>
</div>
