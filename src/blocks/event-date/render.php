<?php
/**
 * Render Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 0.27.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Settings;

$gatherpress_block_instance = Setup::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );
$gatherpress_event          = new Event( $gatherpress_post_id );

$gatherpress_display_type      = $attributes['displayType'] ?? '';
$gatherpress_start_format_attr = $attributes['startDateFormat'] ?? '';
$gatherpress_end_format_attr   = $attributes['endDateFormat'] ?? '';
$gatherpress_separator_attr    = $attributes['separator'] ?? '';
$gatherpress_show_tz_attr      = $attributes['showTimezone'] ?? '';

// Resolve show_start / show_end the same way Event::get_display_datetime does.
$gatherpress_show_start = $gatherpress_display_type
	? in_array( $gatherpress_display_type, array( 'start', 'both' ), true )
	: true;
$gatherpress_show_end   = $gatherpress_display_type
	? in_array( $gatherpress_display_type, array( 'end', 'both' ), true )
	: true;

$gatherpress_settings    = Settings::get_instance();
$gatherpress_date_format = apply_filters( 'gatherpress_date_format', $gatherpress_settings->get( 'date_format' ) );
$gatherpress_time_format = apply_filters( 'gatherpress_time_format', $gatherpress_settings->get( 'time_format' ) );

$gatherpress_start_datetime_format = $gatherpress_start_format_attr
	? $gatherpress_start_format_attr
	: "{$gatherpress_date_format} {$gatherpress_time_format}";
$gatherpress_end_time_format       = $gatherpress_end_format_attr
	? $gatherpress_end_format_attr
	: $gatherpress_time_format;
$gatherpress_end_datetime_format   = $gatherpress_end_format_attr
	? $gatherpress_end_format_attr
	: "{$gatherpress_date_format} {$gatherpress_time_format}";

$gatherpress_human_start = $gatherpress_show_start
	? $gatherpress_event->get_datetime_start( $gatherpress_start_datetime_format )
	: '';
$gatherpress_iso_start   = ( $gatherpress_show_start && ! empty( $gatherpress_human_start ) )
	? $gatherpress_event->get_datetime_start_iso()
	: '';

if ( $gatherpress_show_end ) {
	if ( $gatherpress_show_start && $gatherpress_event->is_same_date() ) {
		$gatherpress_human_end = $gatherpress_event->get_time_end( $gatherpress_end_time_format );
	} else {
		$gatherpress_human_end = $gatherpress_event->get_datetime_end( $gatherpress_end_datetime_format );
	}
} else {
	$gatherpress_human_end = '';
}
$gatherpress_iso_end = ( $gatherpress_show_end && ! empty( $gatherpress_human_end ) )
	? $gatherpress_event->get_datetime_end_iso()
	: '';

$gatherpress_separator = '';
if ( ! empty( $gatherpress_human_start ) && ! empty( $gatherpress_human_end ) ) {
	$gatherpress_separator = $gatherpress_separator_attr
		? $gatherpress_separator_attr
		: __( 'to', 'gatherpress' );
}

$gatherpress_show_tz  = $gatherpress_show_tz_attr
	? 'yes' === $gatherpress_show_tz_attr
	: (bool) $gatherpress_settings->get( 'show_timezone' );
$gatherpress_tz_human = '';
if ( $gatherpress_show_tz ) {
	$gatherpress_tz_human = $gatherpress_event->get_datetime_start( ' T' );
}

$gatherpress_parts = array();

if ( ! empty( $gatherpress_human_start ) ) {
	if ( ! empty( $gatherpress_iso_start ) ) {
		$gatherpress_parts[] = sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( $gatherpress_iso_start ),
			esc_html( $gatherpress_human_start )
		);
	} else {
		$gatherpress_parts[] = esc_html( $gatherpress_human_start );
	}
}

if ( ! empty( $gatherpress_separator ) ) {
	$gatherpress_parts[] = esc_html( $gatherpress_separator );
}

if ( ! empty( $gatherpress_human_end ) ) {
	if ( ! empty( $gatherpress_iso_end ) ) {
		$gatherpress_parts[] = sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( $gatherpress_iso_end ),
			esc_html( $gatherpress_human_end )
		);
	} else {
		$gatherpress_parts[] = esc_html( $gatherpress_human_end );
	}
}

if ( ! empty( $gatherpress_tz_human ) ) {
	$gatherpress_parts[] = esc_html( $gatherpress_tz_human );
}

if ( $gatherpress_parts ) {
	$gatherpress_display = implode( ' ', $gatherpress_parts );
} else {
	$gatherpress_display = esc_html( Event::DATETIME_PLACEHOLDER );
}

// Mirrors core/post-date's isLink: anchor wraps the <time> elements
// so interactive content is not inside <time>.
if ( ! empty( $attributes['isLink'] ) ) {
	$gatherpress_display = sprintf(
		'<a href="%s">%s</a>',
		esc_url( get_permalink( $gatherpress_post_id ) ),
		$gatherpress_display
	);
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php
	echo wp_kses(
		$gatherpress_display,
		array(
			'a'    => array( 'href' => true ),
			'time' => array( 'datetime' => true ),
		)
	);
	?>
</div>
