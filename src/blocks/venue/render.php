<?php
/**
 * Render Venue block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_attributes = $attributes;

$gatherpress_venue = get_post( intval( $attributes['venueId'] ?? 0 ) );

if ( Venue::POST_TYPE !== get_post_type( $gatherpress_venue ) ) {
	return;
}

$gatherpress_venue_information = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );

// (WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase)
// phpcs:ignore
$gatherpress_full_address = $gatherpress_attributes['venueAddress'];
// phpcs:ignore
$gatherpress_venue_phone = $gatherpress_venue_information->phoneNumber;

$gatherpress_attributes['encoded_addy'] = 'https://maps.google.com/maps?q=' . rawurlencode( $gatherpress_full_address ) . '&z=' . rawurlencode( $gatherpress_attributes['zoom'] ) . '&t=' . rawurlencode( $gatherpress_attributes['type'] ) . '&output=embed';
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<div class="gp-venue">
		<div>
		<?php if ( ! empty( $gatherpress_attributes['venueAddress'] ) || ! empty( $gatherpress_attributes['venueName'] ) ) : ?>
			<div class="gp-venue__row">
				<div class="gp-venue__item">
					<div class="gp-venue__icon">
						<div class="dashicons dashicons-location"></div>
					</div>
					<div class="gp-venue__text">
						<?php
						if ( ! empty( $gatherpress_attributes['venueName'] ) ) :
							?>
							<div class="gp-venue__name has-medium-font-size">
								<strong>
									<a href="<?php echo esc_url( get_permalink( $gatherpress_venue->ID ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $gatherpress_attributes['venueName'] ); ?></a>
								</strong>
							</div>
							<?php
						endif;
						if ( ! empty( $gatherpress_attributes['venueAddress'] ) ) :
							?>
							<div class="gp-venue__full-address">
								<?php echo esc_html( $gatherpress_attributes['venueAddress'] ); ?>
							</div>
							<?php
						endif;
						?>
					</div>
				</div>
			</div>
		<?php endif; ?>
			<div class="gp-venue__row gp-venue__gap">
			<?php if ( ! empty( $gatherpress_venue_phone ) || ! empty( $gatherpress_venue_information->website ) ) : ?>
				<?php if ( ! empty( $gatherpress_venue_phone ) ) : ?>
					<div class="gp-venue__item">
						<div class="gp-venue__icon">
							<div class="dashicons dashicons-phone"></div>
						</div>
						<div class="gp-venue__text">
							<div class="gp-venue__phone-number">
								<?php echo esc_html( $gatherpress_venue_phone ); ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $gatherpress_venue_information->website ) ) : ?>
					<div class="gp-venue__item">
						<div class="gp-venue__icon">
							<div class="dashicons dashicons-admin-site-alt3"></div>
						</div>
						<div class="gp-venue__text">
							<div class="gp-venue__website">
								<a href="<?php echo esc_url( $gatherpress_venue_information->website ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $gatherpress_venue_information->website ); ?>
								</a>
							</div>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			</div>
		</div>
	</div>
	<?php if ( $gatherpress_attributes['showMap'] ) : ?>
		<iframe
			src="<?php echo esc_attr( $gatherpress_attributes['encoded_addy'] ); ?>"
			title="<?php echo esc_attr( $gatherpress_full_address ); ?>"
			style="height:<?php echo esc_attr( $gatherpress_attributes['deskHeight'] ); ?>px"
		></iframe>
	<?php endif; ?>
</div>
<?php
