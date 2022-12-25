<?php
/**
 * Render Venue Information block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

if (
	empty( $attributes['name'] ) &&
	empty( $attributes['fullAddress'] ) &&
	empty( $attributes['phoneNumber'] ) &&
	empty( $attributes['website'] )
) {
	return;
}
?>
<div <?php echo esc_attr( get_block_wrapper_attributes() ); ?>>
	<?php if ( ! empty( $attributes['fullAddress'] ) || ! empty( $attributes['name'] ) ) : ?>
		<div class="gp-venue-information__row">
			<div class="gp-venue-information__item">
				<div class="gp-venue-information__icon">
					<div class="dashicons dashicons-location"></div>
				</div>
				<div class="gp-venue-information__text">
					<?php
					if ( ! empty( $attributes['name'] ) ) :
						?>
						<div class="gp-venue-information__name has-medium-font-size">
							<strong>
								<?php echo esc_html( $attributes['name'] ); ?>
							</strong>
						</div>
						<?php
					endif;

					if ( ! empty( $attributes['fullAddress'] ) ) :
						?>
						<div class="gp-venue-information__full-address">
							<?php echo esc_html( $attributes['fullAddress'] ); ?>
						</div>
						<?php
					endif;
					?>
				</div>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $attributes['phoneNumber'] ) || ! empty( $attributes['website'] ) ) : ?>
		<div class="gp-venue-information__row gp-venue-information__gap">
			<?php if ( ! empty( $attributes['phoneNumber'] ) ) : ?>
				<div class="gp-venue-information__item">
					<div class="gp-venue-information__icon">
						<div class="dashicons dashicons-phone"></div>
					</div>
					<div class="gp-venue-information__text">
						<div class="gp-venue-information__phone-number">
							<?php echo esc_html( $attributes['phoneNumber'] ); ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $attributes['website'] ) ) : ?>
				<div class="gp-venue-information__item">
					<div class="gp-venue-information__icon">
						<div class="dashicons dashicons-admin-site-alt3"></div>
					</div>
					<div class="gp-venue-information__text">
						<div class="gp-venue-information__website">
							<a href="<?php echo esc_url( $attributes['website'] ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $attributes['website'] ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
