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

// Get the meta field name from bindings.
$meta_field_name = $attributes['metadata']['bindings']['content']['args']['key'] ?? '';
$field_type      = $attributes['fieldType'] ?? 'text';
$placeholder     = $attributes['placeholder'] ?? '';

if ( empty( $meta_field_name ) ) {
	return;
}

// Get the value from the individual meta field.
$value = get_post_meta( get_the_ID(), $meta_field_name, true );

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
