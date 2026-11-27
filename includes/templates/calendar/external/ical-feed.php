<?php
/**
 * Template for GatherPress ical feeds
 *
 * This template is used to render an ical feed to the browser.
 *
 * It can be replaced by theme authors and will override this existing template.
 *
 * @see /docs/developer/theme-customizations/README.md
 *
 * @package GatherPress\Core\Calendar\External
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\External\Setup;

// Output the .ics feed for the queried events.
Setup::get_instance()->send_ics_file();
