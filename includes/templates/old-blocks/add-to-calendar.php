<?php
/**
 * Container for Add to calendar block.
 *
 * @package GatherPress
 * @subpackage Includes
 * @since 1.0.0
 */

use GatherPress\Includes\Event;

$gatherpress_event = new Event( get_the_ID() );
?>
<div class="gp-add-to-calendar">
	<div class="gp-add-to-calendar__row">
		<div class="gp-add-to-calendar__item">
			<div class="gp-add-to-calendar__icon">
				<div class="dashicons dashicons-calendar"></div>
			</div>
			<div class="gp-add-to-calendar__text">
				<a class="gp-add-to-calendar__init" href="#">
					<?php esc_html_e( 'Add to calendar', 'gatherpress' ); ?>
				</a>
				<div class="gp-add-to-calendar__list" style="display: none;">
					<?php foreach ( $gatherpress_event->get_calendar_links() as $gatherpress_calendar ) : ?>
						<div class="gp-add-to-calendar__list-item">
							<?php if ( ! empty( $gatherpress_calendar['link'] ) ) : ?>
							<a href="<?php echo esc_url( $gatherpress_calendar['link'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php elseif ( ! empty( $gatherpress_calendar['download'] ) ) : ?>
								<a href="<?php echo esc_attr( $gatherpress_calendar['download'] ); ?>" rel="noopener noreferrer">
									<?php endif; ?>
									<?php echo esc_html( $gatherpress_calendar['name'] ); ?>
								</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</div>
