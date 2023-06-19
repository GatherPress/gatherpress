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

$gatherpress_post = get_post();

if ( ! is_a( $gatherpress_post, 'WP_Post' ) ) {
	return;
}

$gatherpress_user_id     = get_current_user_id();
$gatherpress_online_link = $attributes['onlineEventLink'];
$gatherpress_event       = new Event( $gatherpress_post->ID );

$attributes['onlineEventLink'] = ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if (
	! empty( $gatherpress_user_id ) &&
	! empty( $gatherpress_online_link ) &&
	is_object( $gatherpress_event->attendee )
) {
	$gatherpress_user = $gatherpress_event->attendee->get( $gatherpress_user_id );

	// Only show online link if member is attending event.
	if ( 'attending' === $gatherpress_user['status'] ) {
		$attributes['onlineEventLink'] = $gatherpress_online_link; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	}
}
?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> data-gp_block_name="online-event" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
