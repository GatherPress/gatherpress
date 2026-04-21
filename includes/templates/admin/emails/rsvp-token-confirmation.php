<?php
/**
 * Email template for RSVP token confirmation.
 *
 * This template is used to generate RSVP confirmation emails with magic links.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param int    $event_id   The ID of the event for which the email is generated.
 * @param string $token_url  The magic link URL for RSVP confirmation and management.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

if ( ! isset( $event_id, $token_url ) ) {
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
	<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
		<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
			<h2 style="color: #2c3e50;"><?php esc_html_e( 'Thank you for your RSVP!', 'gatherpress' ); ?></h2>

			<p><?php esc_html_e( 'Hi there,', 'gatherpress' ); ?></p>

			<p>
				<?php
				printf(
				/* translators: %s: Event title */
					esc_html__(
						// phpcs:disable Generic.Files.LineLength.TooLong
						'You recently RSVP\'d for %s. To confirm your attendance and complete your RSVP, please click the button below:',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					'<strong>' . wp_kses_post( get_the_title( $event_id ) ) . '</strong>'
				);
				?>
			</p>

			<div style="text-align: center; margin: 30px 0;">
				<?php // phpcs:disable Generic.Files.LineLength.TooLong ?>
				<a href="<?php echo esc_url( $token_url ); ?>" style="background-color: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
				<?php // phpcs:enable Generic.Files.LineLength.TooLong ?>
					<?php esc_html_e( 'Confirm My RSVP', 'gatherpress' ); ?>
				</a>
			</div>

			<p><strong><?php esc_html_e( 'Event Details:', 'gatherpress' ); ?></strong></p>
			<ul>
				<li><strong><?php esc_html_e( 'Event:', 'gatherpress' ); ?></strong> <?php echo wp_kses_post( get_the_title( $event_id ) ); ?></li>
				<li><strong><?php esc_html_e( 'Date:', 'gatherpress' ); ?></strong> <?php echo esc_html( $gatherpress_event->get_display_datetime() ); ?></li>
				<?php if ( ! empty( $gatherpress_venue ) ) : ?>
					<li><strong><?php esc_html_e( 'Location:', 'gatherpress' ); ?></strong> <?php echo wp_kses_post( $gatherpress_venue ); ?></li>
				<?php endif; ?>
			</ul>

			<p><?php esc_html_e( 'If you can\'t click the button above, you can copy and paste this link into your browser:', 'gatherpress' ); ?></p>
			<p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 3px;"><?php echo esc_url( $token_url ); ?></p>

			<p><strong><?php esc_html_e( 'Important:', 'gatherpress' ); ?></strong> <?php esc_html_e( 'This is a magic link! Hold onto this email - you can use this same link anytime to change your RSVP status if your plans change.', 'gatherpress' ); ?></p>

			<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

			<p style="font-size: 14px; color: #666;">
				<?php esc_html_e( 'If you didn\'t RSVP for this event, you can safely ignore this email.', 'gatherpress' ); ?>
			</p>
		</div>
	</body>
</html>
