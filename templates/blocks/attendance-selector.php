<?php
/**
 * Attendance Selector container for React.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_event = new GatherPress\Core\Event( get_the_ID() );

if ( $gatherpress_event->has_event_past() ) {
	return;
}
?>

<div id="gp-attendance-selector-container"></div>
