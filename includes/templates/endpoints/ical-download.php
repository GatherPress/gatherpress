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

use GatherPress\Core\Calendars;
use GatherPress\Core\Event;


// Output the event as an iCalendar (.ics) file.
function gatherpress_output_ics_file() {
    // Start output buffering to capture all output.
    ob_start();

    // Get the event and prepare the filename.
    $event    = new Event( get_queried_object_id() );
    $filename = gatherpress_generate_ics_filename( $event );

    // Send headers for downloading the .ics file.
    gatherpress_send_ics_headers( $filename );

    // Output the generated iCalendar content.
    echo wp_kses_post( Calendars::get_ics_calendar_download() );

    // Get the generated output and calculate file size.
    $ics_content = ob_get_contents();
    $filesize    = strlen( $ics_content );

    // Send the file size in the header.
    header( 'Content-Length: ' . $filesize );

    // End output buffering and clean up.
    ob_end_clean();

    // Output the iCalendar content.
    echo wp_kses_post( $ics_content );

    exit(); // Terminate the script after the file has been output.
}

// Generate the .ics filename based on the event date and name.
function gatherpress_generate_ics_filename( Event $event ) {
    $date      = $event->get_datetime_start( 'Y-m-d' );
    $post_name = $event->event->post_name;
    return $date . '_' . $post_name . '.ics';
}

// Send the necessary headers for the iCalendar file download.
function gatherpress_send_ics_headers( $filename ) {

    $charset = strtolower( get_option( 'blog_charset' ) );

    // Content description
    header( 'Content-Description: File Transfer' );
    
    // Ensure proper content type for the calendar file
    header( 'Content-Type: text/calendar; charset=' . $charset );

    // Force download in most browsers while keeping inline for compatibility.
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    
    // Disable caching to avoid browser caching issues.
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    
    // Prevent content sniffing which might lead to MIME type mismatch.
    header( 'X-Content-Type-Options: nosniff' );
}

// Call the function to output the .ics file.
gatherpress_output_ics_file();