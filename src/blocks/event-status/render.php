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
use GatherPress\Core\Event\Status;

$gatherpress_block_instance = Setup::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );
$gatherpress_event          = new Event( $gatherpress_post_id );
$gatherpress_status         = $gatherpress_event->get_status();
$gatherpress_hide_scheduled = ! isset( $attributes['hideScheduled'] ) || ! empty( $attributes['hideScheduled'] );

$gatherpress_post_type = (string) get_post_type( $gatherpress_post_id );

if ( $gatherpress_hide_scheduled && Status::default_slug( $gatherpress_post_type ) === $gatherpress_status ) {
	return;
}

$gatherpress_label      = $gatherpress_event->get_status_label();
$gatherpress_color      = Status::color( $gatherpress_status );
$gatherpress_default    = Status::default_slug( $gatherpress_post_type );
$gatherpress_attributes = array(
	'class' => sprintf(
		'gatherpress-event-status gatherpress-event-status--is-%s%s',
		sanitize_html_class( $gatherpress_status ),
		// A status other than the default is a change of plan, which the
		// stylesheet marks without needing to know any status by name.
		$gatherpress_status === $gatherpress_default ? '' : ' gatherpress-event-status--is-changed'
	),
);

// The color travels with the status rather than living in the stylesheet, so
// a status a site registers looks like its own.
if ( '' !== $gatherpress_color ) {
	$gatherpress_attributes['style'] = sprintf( '--gatherpress-status-color:%s', $gatherpress_color );
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( $gatherpress_attributes ) ); ?>>
	<span class="gatherpress-event-status__badge">
		<?php echo esc_html( $gatherpress_label ); ?>
	</span>
</div>
