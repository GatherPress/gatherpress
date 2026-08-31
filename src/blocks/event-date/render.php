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

$gatherpress_status  = $gatherpress_event->get_status();
$gatherpress_classes = array();
if ( Event::STATUS_SCHEDULED !== $gatherpress_status ) {
	$gatherpress_classes[] = sprintf( 'gatherpress-event-date--is-%s', sanitize_html_class( $gatherpress_status ) );
}

$gatherpress_wrapper_attributes = empty( $gatherpress_classes )
	? get_block_wrapper_attributes()
	: get_block_wrapper_attributes( array( 'class' => implode( ' ', $gatherpress_classes ) ) );
?>
<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<?php echo wp_kses( $gatherpress_display, array( 'a' => array( 'href' => true ) ) ); ?>
</div>
