<?php
/**
 * Email template for site-wide member messages.
 *
 * This template generates the body of a message sent from the Send Email
 * settings page, so it carries no event data.
 *
 * @package GatherPress\Core
 * @since TBD
 *
 * @param string $message Message content for the email.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $message ) ) {
	return;
}

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<title><?php echo esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></title>
	</head>
	<body style="font-family: Arial, sans-serif;">
		<!-- Site Name -->
		<h1 style="text-align: center;">
			<?php echo esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>
		</h1>

		<!-- Message -->
		<p style="text-align: left;">
			<?php echo wp_kses( nl2br( $message ), array( 'br' => array() ) ); ?>
		</p>
	</body>
</html>
