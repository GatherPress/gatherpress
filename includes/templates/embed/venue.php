<?php
/**
 * Venue details shown inside a post embed.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if (
	! isset( $information )
	|| ( '' === $information['address'] && '' === $information['phone'] && '' === $information['website'] )
) {
	return;
}
?>
<ul class="gatherpress-embed gatherpress-embed--venue">
	<?php if ( '' !== $information['address'] ) : ?>
		<li class="gatherpress-embed__address">
			<strong><?php esc_html_e( 'Address:', 'gatherpress' ); ?></strong>
			<?php echo esc_html( $information['address'] ); ?>
		</li>
	<?php endif; ?>
	<?php if ( '' !== $information['phone'] ) : ?>
		<li class="gatherpress-embed__phone">
			<strong><?php esc_html_e( 'Phone:', 'gatherpress' ); ?></strong>
			<?php echo esc_html( $information['phone'] ); ?>
		</li>
	<?php endif; ?>
	<?php if ( '' !== $information['website'] ) : ?>
		<li class="gatherpress-embed__website">
			<strong><?php esc_html_e( 'Website:', 'gatherpress' ); ?></strong>
			<?php // Plain text: the embed script ignores clicks on links to another host. ?>
			<?php echo esc_html( $information['website'] ); ?>
		</li>
	<?php endif; ?>
</ul>
