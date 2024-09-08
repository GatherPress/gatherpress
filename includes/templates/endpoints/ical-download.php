<?php
/**
 * Template for GatherPress ical file downloads
 *
 * This template is used to render an ical file to the browser.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

// Start collecting all output.
ob_start();

// Prepare event data.
$gatherpress_event    = new Event( get_queried_object_id() );
$gatherpress_filename = $gatherpress_event->get_datetime_start( 'Y-m-d' ) . '_' . $gatherpress_event->event->post_name . '.ics';

// Send file headers.
header( 'Content-Description: .ics for ' . $gatherpress_event->event->post_title );
header( 'Content-Disposition: attachment; filename=' . $gatherpress_filename );
header( 'Content-type: text/calendar; charset=' . get_option( 'blog_charset' ) . ';' );
header( 'Pragma: 0' );
header( 'Expires: 0' );

// Generate ical.
echo wp_kses_post( $gatherpress_event->get_ics_calendar_download() );

// Get collected output and render it.
$gatherpress_ics_file = ob_get_contents();
ob_end_clean();
echo wp_kses_post( $gatherpress_ics_file );

exit();
