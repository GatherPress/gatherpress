<?php
/**
 * Render Online Event v2 block.
 *
 * This block provides context-aware online event link display:
 * - In event context: displays event's online link (RSVP-aware)
 * - In venue context: displays venue's online link
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$current_post_id   = get_the_ID();
$current_post_type = get_post_type();

// Get the link text from block attributes, default to "Online event".
$link_text = $attributes['linkText'] ?? '';
if ( empty( $link_text ) ) {
	$link_text = __( 'Online event', 'gatherpress' );
}

// Determine the full URL and RSVP-aware URL based on context.
$full_url          = '';
$online_event_link = '';
$is_venue          = 'gatherpress_venue' === $current_post_type;

if ( $is_venue ) {
	// Venue context: use venue's link directly (no RSVP check).
	$full_url          = get_post_meta( $current_post_id, 'gatherpress_venue_online_link', true );
	$online_event_link = $full_url;
} else {
	// Event context: get both the full URL and RSVP-aware URL.
	$full_url          = get_post_meta( $current_post_id, 'gatherpress_online_event_link', true );
	$gatherpress_event = new Event( $current_post_id );
	$online_event_link = $gatherpress_event->maybe_get_online_event_link();
}

// Don't render if there's no URL at all.
if ( empty( $full_url ) ) {
	return;
}

$has_link = ! empty( $online_event_link );

$context_json = wp_json_encode(
	array(
		'postId'   => $current_post_id,
		'linkText' => $link_text,
	),
	JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'gatherpress-online-event__link' ) ) ); ?>
	data-wp-interactive="gatherpress"
	data-wp-context='<?php echo $context_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
	data-wp-watch="callbacks.updateOnlineEventLink">
	<?php if ( $has_link ) : ?>
		<a class="gatherpress-online-event__text" href="<?php echo esc_url( $online_event_link ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo esc_html( $link_text ); ?>
		</a>
	<?php else : ?>
		<span class="gatherpress-online-event__text">
			<?php echo esc_html( $link_text ); ?>
		</span>
	<?php endif; ?>
</div>
