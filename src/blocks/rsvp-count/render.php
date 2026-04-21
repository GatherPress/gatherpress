<?php
/**
 * Render RSVP Count block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;

$gatherpress_block_instance = Block::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );

// Only render for events.
if ( Event::POST_TYPE !== get_post_type( $gatherpress_post_id ) ) {
	return;
}

$gatherpress_event     = new Event( $gatherpress_post_id );
$gatherpress_rsvp      = new Rsvp( $gatherpress_post_id );
$gatherpress_responses = $gatherpress_rsvp->responses();

// Get the status from block attributes.
$gatherpress_status = $attributes['status'] ?? 'attending';

// Get count based on status.
$gatherpress_count = 0;
if ( isset( $gatherpress_responses[ $gatherpress_status ]['count'] ) ) {
	$gatherpress_count = intval( $gatherpress_responses[ $gatherpress_status ]['count'] );
}

// Get labels from attributes.
$gatherpress_singular_label = $attributes['singularLabel'] ?? '%d Attendee';
$gatherpress_plural_label   = $attributes['pluralLabel'] ?? '%d Attendees';

// Format the display text.
$gatherpress_label = ( 1 === $gatherpress_count )
	? $gatherpress_singular_label
	: $gatherpress_plural_label;

$gatherpress_display_text = str_replace( '%d', (string) $gatherpress_count, $gatherpress_label );

// Map status to camelCase for JavaScript.
$gatherpress_status_map = array(
	'attending'     => 'attending',
	'waiting_list'  => 'waitingList',
	'not_attending' => 'notAttending',
);

$gatherpress_status_key = $gatherpress_status_map[ $gatherpress_status ] ?? 'attending';

// Build counts array for JavaScript initialization.
$gatherpress_counts = array(
	'attending'     => $gatherpress_responses['attending']['count'] ?? 0,
	'waiting_list'  => $gatherpress_responses['waiting_list']['count'] ?? 0,
	'not_attending' => $gatherpress_responses['not_attending']['count'] ?? 0,
);
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>
	data-wp-interactive="gatherpress"
	<?php echo wp_kses_data( wp_interactivity_data_wp_context( array( 'postId' => $gatherpress_post_id ) ) ); ?>
	data-wp-watch="callbacks.updateRsvpCount"
	data-wp-init="callbacks.initRsvpCount"
	data-status="<?php echo esc_attr( $gatherpress_status_key ); ?>"
	data-singular-label="<?php echo esc_attr( $gatherpress_singular_label ); ?>"
	data-plural-label="<?php echo esc_attr( $gatherpress_plural_label ); ?>"
	data-counts="<?php echo esc_attr( wp_json_encode( $gatherpress_counts ) ); ?>"
>
	<span class="gatherpress-rsvp-count__text"><?php echo esc_html( $gatherpress_display_text ); ?></span>
</div>
