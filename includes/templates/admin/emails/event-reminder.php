<?php
/**
 * Email template for event reminders.
 *
 * Sent to attendees before an event starts.
 *
 * @package GatherPress\Core
 * @since TBD
 *
 * @param int $event_id The ID of the event the reminder is for.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $event_id ) ) {
	return;
}

$gatherpress_event       = new Event( $event_id );
$gatherpress_venue       = $gatherpress_event->get_venue_information()['name'];
$gatherpress_event_url   = (string) get_the_permalink( $event_id );
$gatherpress_online_link = $gatherpress_event->is_online()
	? (string) get_post_meta( $event_id, 'gatherpress_online_event_link', true )
	: '';
$gatherpress_calendars   = $gatherpress_event->get_calendar_links();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<title><?php echo wp_kses_post( get_the_title( $event_id ) ); ?></title>
	</head>
	<body style="font-family: Arial, sans-serif;">
		<p style="margin-bottom: 16px;">
			<?php esc_html_e( 'This is a reminder that you are attending:', 'gatherpress' ); ?>
		</p>

		<!-- Event Title -->
		<h1 style="text-align: center;"><?php echo wp_kses_post( get_the_title( $event_id ) ); ?></h1>

		<!-- Date & Time -->
		<p style="text-align: center;">
			<?php
			/* translators: %s: gatherpress_event date and time. */
			printf( esc_html__( 'Date: %s', 'gatherpress' ), esc_html( $gatherpress_event->get_display_datetime() ) );
			?>
		</p>

		<!-- Venue -->
		<?php if ( ! empty( $gatherpress_venue ) ) : ?>
			<p style="text-align: center;">
				<?php
				printf(
					/* translators: 1: Singular post type label (e.g. "Venue"), 2: Venue name. */
					esc_html__( '%1$s: %2$s', 'gatherpress' ),
					esc_html( Utility::post_type_label( 'singular_name', Venue::POST_TYPE ) ),
					wp_kses_post( $gatherpress_venue )
				);
				?>
			</p>
		<?php endif; ?>

		<!-- Online Link: the reminder only goes to attendees, who can see it. -->
		<?php if ( '' !== $gatherpress_online_link ) : ?>
			<p style="text-align: center;">
				<?php esc_html_e( 'Online:', 'gatherpress' ); ?>
				<a href="<?php echo esc_url( $gatherpress_online_link ); ?>"><?php echo esc_html( $gatherpress_online_link ); ?></a>
			</p>
		<?php endif; ?>

		<!-- Add to Calendar -->
		<?php if ( ! empty( $gatherpress_calendars ) ) : ?>
			<p style="text-align: center;">
				<?php esc_html_e( 'Add to calendar:', 'gatherpress' ); ?>
				<?php foreach ( $gatherpress_calendars as $gatherpress_calendar ) : ?>
					<a href="<?php echo esc_url( $gatherpress_calendar['link'] ?? $gatherpress_calendar['download'] ?? '' ); ?>" style="margin: 0 4px;">
						<?php echo esc_html( $gatherpress_calendar['name'] ); ?>
					</a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<!-- Change RSVP -->
		<div style="text-align: center; margin-top: 20px;">
			<a href="<?php echo esc_url( $gatherpress_event_url ); ?>" style="background-color: #0a58ca; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; display: inline-block;">
				<?php esc_html_e( 'View or change your RSVP', 'gatherpress' ); ?>
			</a>
		</div>
	</body>
</html>
