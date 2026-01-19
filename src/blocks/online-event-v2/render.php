<?php
/**
 * Render Online Event v2 block.
 *
 * This block provides context-aware online event link fetching:
 * - In event context: displays event's online link
 * - In venue context: displays venue's online link, falls back to event's link if empty
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$online_event_link = '';
$current_post_id   = get_the_ID();
$current_post_type = get_post_type();

// Determine the online event link based on context.
if ( 'gatherpress_venue' === $current_post_type ) {
	// Venue context: try venue's link first, then fallback to event.
	$venue_online_link = get_post_meta( $current_post_id, 'gatherpress_venue_online_link', true );

	if ( ! empty( $venue_online_link ) ) {
		$online_event_link = $venue_online_link;
	} else {
		// Fallback to event's online link.
		// We need to get the current event ID from the global context if available.
		// For now, we'll check if there's a global event context.
		global $post;
		if ( isset( $post ) && 'gatherpress_event' === get_post_type( $post ) ) {
			$gatherpress_event = new Event( $post->ID );
			$online_event_link = $gatherpress_event->maybe_get_online_event_link();
		}
	}
} else {
	// Event context: get event's online link.
	$gatherpress_event = new Event( $current_post_id );
	$online_event_link = $gatherpress_event->maybe_get_online_event_link();
}

if ( empty( $online_event_link ) ) {
	return;
}

$attributes['onlineEventLink'] = $online_event_link; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> data-gatherpress_block_name="online-event" data-gatherpress_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
