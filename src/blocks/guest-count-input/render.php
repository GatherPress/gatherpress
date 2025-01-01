<?php
/**
 * Render Guest Count Input block.
 *
 * Dynamically renders a disabled input for specifying guest count with a customizable label.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
$gatherpress_label           = ! empty( $attributes['label'] ) ? $attributes['label'] : __( 'Number of guests?', 'gatherpress' );
$gatherpress_max_guest_limit = get_post_meta( get_the_ID(), 'gatherpress_max_guest_limit', true );
$gatherpress_styles          = sprintf(
	'text-align:%s;',
	esc_attr( isset( $attributes['textAlign'] ) ? $attributes['textAlign'] : 'left' )
);

$gatherpress_kses_defaults = wp_kses_allowed_html( 'post' );
$gatherpress_allowed_tags  = array_merge(
	$gatherpress_kses_defaults,
	array(
		'input' => array(
			'type'        => true,
			'placeholder' => true,
			'aria-label'  => true,
			'min'         => true,
			'max'         => true,
		),
		'label' => array(),
	)
);

printf(
	'<p %1$s><label>%2$s</label><input data-wp-interactive="gatherpress" data-wp-on--change="actions.updateGuestCount" type="number" placeholder="0" aria-label="%3$s" min="0" max="%4$d"></p>',
	wp_kses_data( get_block_wrapper_attributes() ),
	wp_kses_post( $gatherpress_label ),
	esc_html__( 'Enter the number of guests', 'gatherpress' ),
	intval( $gatherpress_max_guest_limit )
);
