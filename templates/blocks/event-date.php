<?php
/**
 * Placeholder for Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_event = new \GatherPress\Core\Event( get_the_ID() );
?>
<div><?php echo esc_html( $gatherpress_event->get_display_datetime() ); ?></div>
