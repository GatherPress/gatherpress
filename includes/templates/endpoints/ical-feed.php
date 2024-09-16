<?php
/**
 * Template for GatherPress ical feeds
 *
 * This template is used to render an ical feed to the browser.
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

	// Prepare the filename.
	$filename = Calendars::generate_ics_filename();

	// Send headers for downloading the .ics file.
    Calendars::send_ics_headers( $filename );

	// Output the generated iCalendar content.
	echo wp_kses_post( Calendars::get_ics_calendar_feed() );

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

// Call the function to output the .ics file.
gatherpress_output_ics_file();
