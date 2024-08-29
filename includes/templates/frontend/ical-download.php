<?php


use GatherPress\Core\Event;

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//Collect output
ob_start();


$event    = new Event( get_queried_object_id() );
$filename = $event->event->post_name . '_' . intval( $event->event->ID ) . '.ics';

// File header
header( 'Content-Description: File GP Transfer' );
header( 'Content-Disposition: attachment; filename=' . $filename );
header( 'Content-type: text/calendar; charset=' . get_option('blog_charset').';');
header( 'Pragma: 0');
header( 'Expires: 0');

// 5. Output
echo $event->get_ics_calendar_download();

//Collect output and echo
$ical = ob_get_contents();
ob_end_clean();
echo $ical;
exit();