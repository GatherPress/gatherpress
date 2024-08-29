<?php


use GatherPress\Core\Event;

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$event    = new Event( get_queried_object_id() );
$filename = $event->event->post_name . '_' . intval( $event->event->ID ) . '.ics';

// 4. Set headers
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// 5. Output
echo $event->get_ics_calendar_download();
die();