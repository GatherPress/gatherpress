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

$gatherpress_venue      = Venue::get_instance();
$gatherpress_venue_meta = $gatherpress_venue->get_venue_meta( get_the_ID(), get_post_type() );

// Get the meta field name from bindings.
$meta_field_name = $attributes['metadata']['bindings']['content']['args']['key'] ?? '';
$field_type      = $attributes['fieldType'] ?? 'text';
$placeholder     = $attributes['placeholder'] ?? '';

if ( empty( $meta_field_name ) ) {
	return;
}

// Map our meta field names to the venue meta array keys.
$meta_key_map = array(
	'gatherpress_venue_address' => 'fullAddress',
	'gatherpress_venue_phone'   => 'phoneNumber',
	'gatherpress_venue_website' => 'website',
);

$meta_key = $meta_key_map[ $meta_field_name ] ?? '';
$value    = ! empty( $meta_key ) ? ( $gatherpress_venue_meta[ $meta_key ] ?? '' ) : '';

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
