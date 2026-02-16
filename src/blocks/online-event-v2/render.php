<?php
/**
 * Render Online Event v2 block.
 *
 * This block provides context-aware online event link display
 * for events with RSVP-aware URL handling.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Tooltip;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_current_post_id   = get_the_ID();
$gatherpress_current_post_type = get_post_type();

// Get the link text from block attributes, default to "Online event".
$gatherpress_link_text = $attributes['linkText'] ?? '';
if ( empty( $gatherpress_link_text ) ) {
	$gatherpress_link_text = __( 'Online event', 'gatherpress' );
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
	<?php $gatherpress_allowed_html = Tooltip::get_allowed_html(); ?>
	<?php if ( $gatherpress_has_link ) : ?>
		<a class="gatherpress-online-event__text" href="<?php echo esc_url( $gatherpress_online_event_link ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo wp_kses( $gatherpress_link_text, $gatherpress_allowed_html ); ?>
		</a>
	<?php else : ?>
		<span class="gatherpress-online-event__text">
			<?php echo wp_kses( $gatherpress_link_text, $gatherpress_allowed_html ); ?>
		</span>
	<?php endif; ?>
</div>
