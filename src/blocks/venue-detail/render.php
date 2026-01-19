<?php
/**
 * Render Venue Detail block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$field_type  = $attributes['fieldType'] ?? 'text';
$placeholder = $attributes['placeholder'] ?? '';

// Get venue information from JSON field.
$venue_info_json = get_post_meta( get_the_ID(), 'gatherpress_venue_information', true );
$venue_info      = json_decode( $venue_info_json, true );

if ( ! is_array( $venue_info ) ) {
	return;
}

// Map field type to JSON field name.
$field_mapping = array(
	'address' => 'fullAddress',
	'phone'   => 'phoneNumber',
	'url'     => 'website',
);

$json_field = $field_mapping[ $field_type ] ?? '';

if ( empty( $json_field ) ) {
	return;
}

$value = $venue_info[ $json_field ] ?? '';

if ( empty( $value ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes();

switch ( $field_type ) {
	case 'address':
		printf(
			'<div %s><address>%s</address></div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $value )
		);
		break;

	case 'phone':
		printf(
			'<div %s><a href="tel:%s">%s</a></div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $value ),
			esc_html( $value )
		);
		break;

	case 'url':
		printf(
			'<div %s><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $value ),
			esc_html( $value )
		);
		break;

	default:
		printf(
			'<div %s>%s</div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $value )
		);
		break;
}
