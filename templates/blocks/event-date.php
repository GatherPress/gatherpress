<?php
/**
 * Placeholder for Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_event = new GatherPress\Core\Event( get_the_ID() );
?>
<div class="gp-event-date">
	<div class="gp-event-date__row">
		<div class="gp-event-date__item">
			<div class="gp-event-date__icon">
				<div class="dashicons dashicons-clock"></div>
			</div>
			<div class="gp-event-date__text">
				<?php echo esc_html( $gatherpress_event->get_display_datetime() ); ?>
			</div>
		</div>
	</div>
	<div class="gp-event-date__row">
		<div class="gp-event-date__item">
			<div class="gp-event-date__icon">
				<div class="dashicons dashicons-calendar"></div>
			</div>
			<div class="gp-event-date__text">
				<div class="gp-add-to-calendar">
					<a class="gp-add-to-calendar__init" href="#">
						<?php esc_html_e( 'Add to calendar', 'gatherpress' ); ?>
					</a>
					<div class="gp-add-to-calendar__list" style="display: none;">
						<?php foreach ( $gatherpress_event->get_calendar_links() as $gatherpress_calendar ) : ?>
						<div class="gp-add-to-calendar__item">
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
</div>


