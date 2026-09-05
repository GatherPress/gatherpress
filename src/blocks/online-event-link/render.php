<?php
/**
 * Render Online Event block.
 *
 * This block provides context-aware online event link display
 * for events with RSVP-aware URL handling.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

// Get the event post ID from block context (set by online-event via providesContext).
// Fall back to get_the_ID() for standalone usage outside venue.
$gatherpress_current_post_id = ! empty( $block->context['postId'] )
	? (int) $block->context['postId']
	: get_the_ID();

$gatherpress_current_post_type = get_post_type( $gatherpress_current_post_id );

// Get the link text from block attributes. The default is built here rather
// than seeded into the block, so the wording is not frozen into every post
// that uses it and stays updatable.
$gatherpress_link_text = $attributes['linkText'] ?? '';

if ( empty( $gatherpress_link_text ) ) {
	$gatherpress_link_text = esc_html__( 'Online event', 'gatherpress' );

	// Only events hold the link back until someone is attending, so the
	// caveat belongs to them and not to anything else using this block.
	if ( Event::POST_TYPE === $gatherpress_current_post_type ) {
		$gatherpress_link_text = sprintf(
			'<span class="gatherpress-tooltip" data-gatherpress-tooltip="%1$s">%2$s</span>',
			esc_attr__( 'link available for attendees only', 'gatherpress' ),
			$gatherpress_link_text
		);
	}
}

// Determine the full URL and RSVP-aware URL.
$gatherpress_full_url          = '';
$gatherpress_online_event_link = '';

// Only events have online event links.
if ( Event::POST_TYPE === $gatherpress_current_post_type ) {
	$gatherpress_full_url          = get_post_meta( $gatherpress_current_post_id, 'gatherpress_online_event_link', true );
	$gatherpress_event             = new Event( $gatherpress_current_post_id );
	$gatherpress_online_event_link = $gatherpress_event->maybe_get_online_event_link();
}

$gatherpress_has_link = ! empty( $gatherpress_online_event_link );

$gatherpress_context_json = wp_json_encode(
	array(
		'postId'   => $gatherpress_current_post_id,
		'linkText' => $gatherpress_link_text,
	),
	JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'gatherpress-online-event__link' ) ) ); ?>
	data-wp-interactive="gatherpress"
	data-wp-context='<?php echo $gatherpress_context_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
	data-wp-watch="callbacks.updateOnlineEventLink">
	<?php if ( $gatherpress_has_link ) : ?>
		<a class="gatherpress-online-event__text" href="<?php echo esc_url( $gatherpress_online_event_link ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo wp_kses_post( $gatherpress_link_text ); ?>
		</a>
	<?php else : ?>
		<span class="gatherpress-online-event__text">
			<?php echo wp_kses_post( $gatherpress_link_text ); ?>
		</span>
	<?php endif; ?>
</div>
