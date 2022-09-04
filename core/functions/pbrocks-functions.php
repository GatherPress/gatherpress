<?php
/**
 * Class is responsible for loading all static assets.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define Template
 *
 * @return void
 */
function pbrocks_register_block_template() {
	$post_type_object           = get_post_type_object( 'gp_event' );
	$post_type_object->template = array(
		'template' => array(
			array(
				'core/heading',
				array(
					'content' => 'An Event Title ...',
				),
			),
			array(
				'core/paragraph',
				array(
					'content' => 'A Description about the event ...',
				),
			),
			array(
				'core/columns',
				array(),
				array(
					'core/column',
					array(),
					array(
						'gatherpress-event/event-start',
						array(),
					),
				),
				array(
					'core/column',
					array(),
					array(
						'gatherpress-event/event-end',
						array(),
					),
				),
			),
		),
	);
	$post_type_object->template_lock = 'all';
}
add_action( 'init', 'pbrocks_register_block_template' );
