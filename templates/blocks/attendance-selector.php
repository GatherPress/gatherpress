<?php
/**
 * Attendance Selector container for React.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$event = new GatherPress\Core\Event( get_the_ID() );

if ( ! is_user_logged_in() || $event->has_event_past() ) {
	return;
}
?>

<div id="gp-attendance-selector-container"></div>
