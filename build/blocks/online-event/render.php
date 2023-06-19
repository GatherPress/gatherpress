<?php
/**
 * Render Online Event block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Event;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$post = get_post();

if ( ! is_a( $post, 'WP_Post' ) ) {
	return;
}

$user_id     = get_current_user_id();
$online_link = $attributes['onlineEventLink'];
$event       = new Event( $post->ID );

$attributes['onlineEventLink'] = '';

if (
	! empty( $user_id ) &&
	! empty( $online_link ) &&
	is_object( $event->attendee )
) {
	$user = $event->attendee->get( $user_id );

	// Only show online link if member is attending event.
	if ( 'attending' === $user['status'] ) {
		$attributes['onlineEventLink'] = $online_link;
	}
}
?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> data-gp_block_name="online-event" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
