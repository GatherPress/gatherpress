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

$gatherpress_event             = new Event( get_the_ID() );
$attributes['onlineEventLink'] = $gatherpress_event->maybe_get_online_event_link(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> data-gatherpress_block_name="online-event" data-gatherpress_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
