<?php
/**
 * Event details shown inside a post embed.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $datetime, $venue_name, $address, $is_online, $state, $rsvp_link ) ) {
	return;
}
?>
<ul class="gatherpress-embed gatherpress-embed--event">
	<li class="gatherpress-embed__datetime">
		<strong><?php esc_html_e( 'Date:', 'gatherpress' ); ?></strong>
		<?php echo esc_html( $datetime ); ?>
	</li>
	<?php if ( '' !== $venue_name ) : ?>
		<li class="gatherpress-embed__venue">
			<strong><?php esc_html_e( 'Venue:', 'gatherpress' ); ?></strong>
			<?php echo esc_html( $venue_name ); ?>
			<?php if ( '' !== $address ) : ?>
				<span class="gatherpress-embed__venue-address"><?php echo esc_html( $address ); ?></span>
			<?php endif; ?>
		</li>
	<?php endif; ?>
	<?php if ( $is_online ) : ?>
		<li class="gatherpress-embed__online"><?php esc_html_e( 'Online event', 'gatherpress' ); ?></li>
	<?php endif; ?>
	<?php if ( 'canceled' === $state ) : ?>
		<li class="gatherpress-embed__status"><?php esc_html_e( 'This event is canceled.', 'gatherpress' ); ?></li>
	<?php elseif ( 'past' === $state ) : ?>
		<li class="gatherpress-embed__status"><?php esc_html_e( 'This event has passed.', 'gatherpress' ); ?></li>
	<?php endif; ?>
</ul>
<?php if ( '' !== $rsvp_link ) : ?>
	<p class="gatherpress-embed__rsvp">
		<a href="<?php echo esc_url( $rsvp_link ); ?>" target="_top"><?php esc_html_e( 'RSVP', 'gatherpress' ); ?></a>
	</p>
<?php endif; ?>
