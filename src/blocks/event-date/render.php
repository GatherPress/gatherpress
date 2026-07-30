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
// placeholder carrying the event's GMT datetimes and its own timezone for
// `view.js` to fill. It stays hidden for a reader already in the event's
// timezone, and for a reader without JavaScript, where an unconverted second
// copy of the same time would be worse than nothing.
$gatherpress_viewer_time = '';

if ( ! empty( $attributes['showViewerTime'] ) ) {
	$gatherpress_datetime = $gatherpress_event->get_datetime();

	if ( ! empty( $gatherpress_datetime['datetime_start_gmt'] ) ) {
		$gatherpress_viewer_time = sprintf(
			'<span class="gatherpress-event-date__viewer-time" data-gatherpress-start-gmt="%1$s" data-gatherpress-end-gmt="%2$s" data-gatherpress-timezone="%3$s" hidden></span>',
			esc_attr( $gatherpress_datetime['datetime_start_gmt'] ),
			esc_attr( $gatherpress_datetime['datetime_end_gmt'] ?? '' ),
			esc_attr( $gatherpress_datetime['timezone'] ?? '' )
		);
	}
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php echo wp_kses( $gatherpress_display, array( 'a' => array( 'href' => true ) ) ); ?>
	<?php
	echo wp_kses(
		$gatherpress_viewer_time,
		array(
			'span' => array(
				'class'                      => true,
				'hidden'                     => true,
				'data-gatherpress-start-gmt' => true,
				'data-gatherpress-end-gmt'   => true,
				'data-gatherpress-timezone'  => true,
			),
		)
	);
	?>
</div>
