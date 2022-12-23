<?php
/**
 * Placeholder for Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Event;

$gatherpress_event = new Event( get_the_ID() );
$wrapper_attributes = get_block_wrapper_attributes();
ob_start();
?>
<!-- <div class="gatherpress-event-date"> -->
	<div class="gatherpress-event-date__row">
		<div class="gatherpress-event-date__item">
			<div class="gatherpress-event-date__icon">
				<div class="dashicons dashicons-clock"></div>
			</div>
			<div class="gatherpress-event-date__text">
				<?php echo esc_html( $gatherpress_event->get_display_datetime() ); ?>
			</div>
		</div>
	</div>
<!-- </div> -->
<?php
$block_content = ob_get_clean();

printf(
	'<div %s>%s</div>',
	$wrapper_attributes,
	$block_content
);

