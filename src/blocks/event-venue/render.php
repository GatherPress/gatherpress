<?php
/**
 * Render Venue block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}
$gatherpress_venue = Venue::get_instance()->get_venue_post_from_term_slug( (string) $attributes['slug'] );

if ( Venue::POST_TYPE !== get_post_type( $gatherpress_venue ) ) {
	return;
}

$gatherpress_venue_name         = get_the_title( $gatherpress_venue->ID );
$gatherpress_venue_information  = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );
$gatherpress_venue_full_address = $gatherpress_venue_information->fullAddress; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
$gatherpress_venue_phone_number = $gatherpress_venue_information->phoneNumber; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
$gatherpress_venue_website      = $gatherpress_venue_information->website;
$attributes['fullAddress']      = $gatherpress_venue_full_address; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<div class="gp-venue">
		<?php if ( ! empty( $gatherpress_venue_full_address ) || ! empty( $gatherpress_venue_name ) ) : ?>
			<div class="gp-venue__row">
				<div class="gp-venue__item">
					<div class="gp-venue__icon">
						<div class="dashicons dashicons-location"></div>
					</div>
					<div class="gp-venue__text">
						<?php
						if ( ! empty( $gatherpress_venue_name ) ) :
							?>
							<div class="gp-venue__name has-medium-font-size">
								<strong>
									<a href="<?php echo esc_url( get_permalink( $gatherpress_venue->ID ) ); ?>">
										<?php echo esc_html( $gatherpress_venue_name ); ?>
									</a>
								</strong>
							</div>
							<?php
						endif;
						if ( ! empty( $gatherpress_venue_full_address ) ) :
							?>
							<div class="gp-venue__full-address">
								<?php echo esc_html( $gatherpress_venue_full_address ); ?>
							</div>
							<?php
						endif;
						?>
					</div>
				</div>
			</div>
		<?php endif; ?>
			<div class="gp-venue__row gp-venue__gap">
			<?php if ( ! empty( $gatherpress_venue_phone_number ) || ! empty( $gatherpress_venue_website ) ) : ?>
				<?php if ( ! empty( $gatherpress_venue_phone_number ) ) : ?>
					<div class="gp-venue__item">
						<div class="gp-venue__icon">
							<div class="dashicons dashicons-phone"></div>
						</div>
						<div class="gp-venue__text">
							<div class="gp-venue__phone-number">
								<?php echo esc_html( $gatherpress_venue_phone_number ); ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $gatherpress_venue_website ) ) : ?>
					<div class="gp-venue__item">
						<div class="gp-venue__icon">
							<div class="dashicons dashicons-admin-site-alt3"></div>
						</div>
						<div class="gp-venue__text">
							<div class="gp-venue__website">
								<a href="<?php echo esc_url( $gatherpress_venue_website ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $gatherpress_venue_website ); ?>
								</a>
							</div>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php if ( $attributes['mapShow'] ) : ?>
			<div data-gp_block_name="map-embed" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
		<?php endif; ?>
	</div>
</div>
