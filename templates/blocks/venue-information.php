<?php
/**
 * Template for Venue Information block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $gatherpress_block_attrs ) || ! is_array( $gatherpress_block_attrs ) ) {
	return;
}

if (
	empty( $gatherpress_block_attrs['fullAddress'] ) &&
	empty( $gatherpress_block_attrs['phoneNumber'] ) &&
	empty( $gatherpress_block_attrs['website'] )
) {
	return;
}
?>
<div class="gp-venue-information">
	<?php if ( ! empty( $gatherpress_block_attrs['fullAddress'] ) ) : ?>
		<div class="gp-venue-information__row">
			<div class="gp-venue-information__item">
				<div class="gp-venue-information__icon">
					<div class="dashicons dashicons-location"></div>
				</div>
				<div class="gp-venue-information__text">
					<?php echo esc_html( $gatherpress_block_attrs['fullAddress'] ); ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $gatherpress_block_attrs['phoneNumber'] ) || ! empty( $gatherpress_block_attrs['website'] ) ) : ?>
		<div class="gp-venue-information__row gp-venue-information__gap">
			<?php if ( ! empty( $gatherpress_block_attrs['phoneNumber'] ) ) : ?>
				<div class="gp-venue-information__item">
					<div class="gp-venue-information__icon">
						<div class="dashicons dashicons-phone"></div>
					</div>
					<div class="gp-venue-information__text">
						<?php echo esc_html( $gatherpress_block_attrs['phoneNumber'] ); ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $gatherpress_block_attrs['website'] ) ) : ?>
				<div class="gp-venue-information__item">
					<div class="gp-venue-information__icon">
						<div class="dashicons dashicons-admin-site-alt3"></div>
					</div>
					<div class="gp-venue-information__text">
						<a href="<?php echo esc_url( $gatherpress_block_attrs['website'] ); ?>" target="_blank">
							<?php echo esc_html( $gatherpress_block_attrs['website'] ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
