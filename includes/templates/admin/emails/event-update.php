<?php
if ( ! isset( $event_id ) ) {
	return;
}

$event = new \GatherPress\Core\Event( $event_id );
$venue = $event->get_venue_information()['name'];

?>

<!DOCTYPE html>
<html>
	<head>
		<title><?php echo wp_kses_post( get_the_title( $event_id ) ); ?></title>
	</head>
	<body style="font-family: Arial, sans-serif;">

		<!-- Feature Image -->
		<img src="<?php echo esc_url( get_the_post_thumbnail_url( $event_id, 'full' ) ); ?>" alt="<?php esc_attr_e( 'Event Image', 'gatherpress' ); ?>" style="max-width: 100%;">

		<!-- Event Title -->
		<h1 style="text-align: center;"><?php echo wp_kses_post( get_the_title( $event_id ) ); ?></h1>

		<!-- Date & Time -->
		<p style="text-align: center;"><?php printf( esc_html__( 'Date: %s', 'gatherpress'), $event->get_display_datetime() ); ?></p>

		<!-- Venue -->
		<?php if ( ! empty( $venue ) ) : ?>
			<p style="text-align: center;"><?php printf( esc_html__( 'Venue: %s', 'gatherpress'), $venue ); ?></p>
		<?php endif; ?>

		<!-- RSVP Button -->
		<div style="text-align: center; margin-top: 20px;">
			<a href="<?php echo esc_url( get_the_permalink( $event_id ) ); ?>" style="background-color: #007bff; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
				<?php esc_html_e( 'RSVP Now', 'gatherpress' ); ?>
			</a>
		</div>

		<!-- Excerpt -->
		<p style="text-align: left;"><?php echo esc_html( get_the_excerpt( $event_id ) ); ?></p>

	</body>
</html>
