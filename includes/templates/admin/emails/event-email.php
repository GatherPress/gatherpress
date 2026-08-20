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
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $event_id, $message ) ) {
	return;
}

$gatherpress_event       = new Event( $event_id );
$gatherpress_event_image = get_post_thumbnail_id( $event_id );
$gatherpress_venue       = $gatherpress_event->get_venue_information()['name'];

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
			echo wp_get_attachment_image(
				$gatherpress_event_image,
				'full',
				false,
				array(
					'alt'   => esc_attr(
						sprintf(
							/* translators: %s: Singular post type label, e.g. "Event". */
							__( '%s Image', 'gatherpress' ),
							Utility::post_type_label( 'singular_name', (string) get_post_type( $event_id ) )
						)
					),
					'style' => 'max-width: 100%;',
				)
			);
			?>
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

		<!-- RSVP Button: only when registration is still open. -->
		<?php
		if (
			! $gatherpress_event->has_event_past()
			&& ( new Rsvp( $event_id ) )->is_enabled()
		) :
			$gatherpress_rsvp_url = (string) get_the_permalink( $event_id );
			?>
			<div style="text-align: center; margin-top: 20px;">
				<a href="<?php echo esc_url( $gatherpress_rsvp_url ); ?>" style="background-color: #007bff; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
					<?php esc_html_e( 'RSVP Now', 'gatherpress' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<!-- Excerpt -->
		<p style="text-align: left;"><?php echo esc_html( get_the_excerpt( $event_id ) ); ?></p>

	</body>
</html>
