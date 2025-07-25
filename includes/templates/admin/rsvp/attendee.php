<?php
/**
 * User Date Time Settings Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $comment, $username, $email, $ip_search_url ) ) {
	return;
}
?>
<div class="gatherpress-attendee-info">
	<div>
		<?php echo get_avatar( get_comment( $comment['comment_ID'] ), 60, 'mystery' ); ?>
	</div>
	<div>
		<strong>
			<?php echo esc_html( $username ); ?>
		</strong>
		<br />
		<a href="<?php echo esc_url( 'mailto:' . $email ); ?>">
			<?php echo esc_html( $email ); ?>
		</a>
		<br />
		<a href="<?php echo esc_url( $ip_search_url ); ?>">
			<?php echo esc_html( $comment['comment_author_IP'] ); ?>
		</a>
	</div>
</div>
