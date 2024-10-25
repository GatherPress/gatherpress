<?php
/**
 * Template for GatherPress ical file downloads
 *
 * This template is used to render an ical file to the browser.
 *
 * It can be replaced by theme authors and will override this existing template.
 *
 * @see /docs/developer/theme-customizations/README.md
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendars;

// Call the function to output the .ics file.
Calendars::send_ics_file();
