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
?>
<div class="gp-venue-information">
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
	<div class="gp-venue-information__row gp-venue-information__gap">
		<div class="gp-venue-information__item">
			<div class="gp-venue-information__icon">
				<div class="dashicons dashicons-phone"></div>
			</div>
			<div class="gp-venue-information__text">
				<?php echo esc_html( $gatherpress_block_attrs['phoneNumber'] ); ?>
			</div>
		</div>
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
	</div>
</div>
