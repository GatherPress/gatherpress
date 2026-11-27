<?php
/**
 * Email template for event notifications.
 *
 * This template is used to generate email notifications for events in GatherPress.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 *
 * @param int    $event_id The ID of the event for which the email is generated.
 * @param string $message  Optional message content for the email.
 * @param bool   $show_rsvp_button Optional. Whether to render the RSVP button. Defaults to true,
 *                                 so a caller that omits it keeps the button.
 * @param bool   $show_online_link    Optional. Whether to show an online event's link. Defaults to false.
 * @param bool   $show_calendar_links Optional. Whether to show add to calendar links. Defaults to false.
 * @param string $rsvp_button_label   Optional. The RSVP button's text. Defaults to "RSVP Now".
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $event_id, $message ) ) {
	return;
}

$gatherpress_event       = new Event( $event_id );
$gatherpress_event_image = get_post_thumbnail_id( $event_id );
$gatherpress_venue       = $gatherpress_event->get_venue_information()['name'];

// An email about a spot the attendee already holds, such as a waiting list
// promotion, passes false so the button does not read as "RSVP again".
$gatherpress_show_rsvp_button = $show_rsvp_button ?? true;
$gatherpress_rsvp_button_text = $rsvp_button_label ?? __( 'RSVP Now', 'gatherpress' );

// Only emails to attendees, such as the reminder, show the online link.
$gatherpress_online_link = ! empty( $show_online_link ) && $gatherpress_event->is_online()
	? (string) get_post_meta( $event_id, 'gatherpress_online_event_link', true )
	: '';
$gatherpress_calendars   = ! empty( $show_calendar_links ) ? $gatherpress_event->get_calendar_links() : array();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<title><?php echo wp_kses_post( get_the_title( $event_id ) ); ?></title>
	</head>
	<body style="font-family: Arial, sans-serif;">
		<?php if ( ! empty( $message ) ) : ?>
			<p style="margin-bottom: 16px;">
				<?php echo wp_kses( nl2br( $message ), array( 'br' => array() ) ); ?>
			</p>
		<?php endif; ?>
		<?php if ( $gatherpress_event_image ) : ?>
			<!-- Featured Image -->
			<?php
			$gatherpress_image = wp_get_attachment_image_src( $gatherpress_event_image, 'large' );
			$gatherpress_alt   = sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s Image', 'gatherpress' ),
				Utility::post_type_label( 'singular_name', (string) get_post_type( $event_id ) )
			);
			?>
			<?php if ( is_array( $gatherpress_image ) ) : ?>
				<?php $gatherpress_dimensions = wp_constrain_dimensions( $gatherpress_image[1], $gatherpress_image[2], 600, 0 ); ?>
				<img
					src="<?php echo esc_url( $gatherpress_image[0] ); ?>"
					width="<?php echo esc_attr( (string) $gatherpress_dimensions[0] ); ?>"
					height="<?php echo esc_attr( (string) $gatherpress_dimensions[1] ); ?>"
					alt="<?php echo esc_attr( $gatherpress_alt ); ?>"
					style="max-width: 100%; height: auto;"
				/>
			<?php endif; ?>
		<?php endif; ?>

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

		<!-- Online Link -->
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

		<!-- RSVP Button: only when registration is still open. -->
		<?php
		if (
			$gatherpress_show_rsvp_button
			&& ! $gatherpress_event->has_event_past()
			&& ( new Rsvp( $event_id ) )->is_enabled()
		) :
			$gatherpress_rsvp_url = (string) get_the_permalink( $event_id );
			?>
			<div style="text-align: center; margin-top: 20px;">
				<a href="<?php echo esc_url( $gatherpress_rsvp_url ); ?>" style="background-color: #0a58ca; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px; display: inline-block;">
					<?php echo esc_html( $gatherpress_rsvp_button_text ); ?>
				</a>
			</div>
		<?php endif; ?>

		<!-- Excerpt -->
		<p style="text-align: left;"><?php echo esc_html( get_the_excerpt( $event_id ) ); ?></p>

	</body>
</html>
