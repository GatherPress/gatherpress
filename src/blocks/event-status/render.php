<?php
/**
 * Render Event Status block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 0.36.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Setup;
use GatherPress\Core\Event;

$gatherpress_block_instance = Setup::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );
$gatherpress_event          = new Event( $gatherpress_post_id );
$gatherpress_status         = $gatherpress_event->get_status();
$gatherpress_hide_scheduled = ! isset( $attributes['hideScheduled'] ) || ! empty( $attributes['hideScheduled'] );

if ( $gatherpress_hide_scheduled && Event::STATUS_SCHEDULED === $gatherpress_status ) {
	return;
}

$gatherpress_label = $gatherpress_event->get_status_label();
$gatherpress_class = sprintf( 'gatherpress-event-status gatherpress-event-status--is-%s', sanitize_html_class( $gatherpress_status ) );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => $gatherpress_class ) ) ); ?>>
	<span class="gatherpress-event-status__badge">
		<?php echo esc_html( $gatherpress_label ); ?>
	</span>
</div>
