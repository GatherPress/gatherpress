<?php
/**
 * Render Venue Detail block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_field_type  = $attributes['fieldType'] ?? 'text';
$gatherpress_placeholder = $attributes['placeholder'] ?? '';

// 'url' is the input-type name for the website meta field; otherwise the
// fieldType matches the venue meta-key suffix one-to-one.
$gatherpress_meta_field = ( 'url' === $gatherpress_field_type ) ? 'website' : $gatherpress_field_type;

// Allow only known venue fields. Anything else (e.g. fieldType "text") returns.
if ( ! in_array( $gatherpress_meta_field, array( 'address', 'phone', 'website' ), true ) ) {
	return;
}

$gatherpress_meta_key = sprintf( 'gatherpress_%s', $gatherpress_meta_field );

$gatherpress_value = (string) get_post_meta( get_the_ID(), $gatherpress_meta_key, true );

if ( '' === $gatherpress_value ) {
	return;
}

$gatherpress_wrapper_attributes = get_block_wrapper_attributes();

switch ( $gatherpress_field_type ) {
	case 'address':
		printf(
			'<div %s><address class="gatherpress-venue-detail__address">%s</address></div>',
			$gatherpress_wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $gatherpress_value )
		);
		break;

	case 'phone':
		printf(
			'<div %s><a class="gatherpress-venue-detail__phone" href="tel:%s">%s</a></div>',
			$gatherpress_wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $gatherpress_value ),
			esc_html( $gatherpress_value )
		);
		break;

	case 'url':
		$gatherpress_link_target = $attributes['linkTarget'] ?? '_blank';
		$gatherpress_clean_url   = $attributes['cleanUrl'] ?? true;

		// Display URL cleaned or raw based on setting.
		if ( $gatherpress_clean_url ) {
			$gatherpress_display_url = preg_replace( '#^https?://(www\.)?#', '', $gatherpress_value );
			$gatherpress_display_url = rtrim( $gatherpress_display_url, '/' );
		} else {
			$gatherpress_display_url = $gatherpress_value;
		}

		$gatherpress_target_attr = '_blank' === $gatherpress_link_target ? ' target="_blank" rel="noopener noreferrer"' : '';

		printf(
			'<div %s><a class="gatherpress-venue-detail__url" href="%s"%s>%s</a></div>',
			$gatherpress_wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $gatherpress_value ),
			$gatherpress_target_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $gatherpress_display_url )
		);
		break;

	default:
		printf(
			'<div %s><span class="gatherpress-venue-detail__text">%s</span></div>',
			$gatherpress_wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $gatherpress_value )
		);
		break;
}
