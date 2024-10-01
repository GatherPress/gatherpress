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

// Call the function to output the .ics file.
Calendars::send_ics_file();
