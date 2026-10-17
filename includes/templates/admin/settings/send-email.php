<?php
/**
 * Template for the GatherPress "Send Email" settings page.
 *
 * Provides a form for sending a message to every opted-in member of the site.
 *
 * @package GatherPress\Core
 * @since TBD
 *
 * @param int $recipient_count Number of members the message would go out to.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $recipient_count ) ) {
	return;
}
?>

<h2><?php esc_html_e( 'Send Email To Members', 'gatherpress' ); ?></h2>
<p class="description">
	<?php
	esc_html_e(
		'Send a message to every member of this site who has event updates turned on. Members can turn these emails off from their profile, and they are always skipped.',
		'gatherpress'
	);
	?>
</p>

<p class="description">
	<?php
	printf(
		esc_html(
			/* translators: %d: Number of members the message will be sent to. */
			_n(
				'This will email %d member.',
				'This will email %d members.',
				$recipient_count,
				'gatherpress'
			)
		),
		(int) $recipient_count
	);
	?>
</p>

<div class="gatherpress-send-email" data-gatherpress_component_name="send-email">
	<div class="gatherpress-settings-form">
		<div class="gatherpress-settings-form__row">
			<div class="gatherpress-settings-form__label">
				<label for="gatherpress-message-subject"><?php esc_html_e( 'Subject', 'gatherpress' ); ?></label>
			</div>
			<div class="gatherpress-settings-form__field">
				<input
					type="text"
					id="gatherpress-message-subject"
					class="large-text"
					aria-describedby="gatherpress-message-subject-description"
				/>
				<p class="description" id="gatherpress-message-subject-description">
					<?php esc_html_e( 'Leave blank to use "Message from" and the site name.', 'gatherpress' ); ?>
				</p>
			</div>
		</div>
		<div class="gatherpress-settings-form__row">
			<div class="gatherpress-settings-form__label">
				<label for="gatherpress-message-body"><?php esc_html_e( 'Message', 'gatherpress' ); ?></label>
			</div>
			<div class="gatherpress-settings-form__field">
				<textarea
					id="gatherpress-message-body"
					class="large-text"
					rows="8"
				></textarea>
			</div>
		</div>
	</div>

	<output class="gatherpress-send-email__status gatherpress--is-hidden">
		<span class="spinner"></span>
		<span class="gatherpress-send-email__status-text"></span>
	</output>

	<p>
		<button type="button" class="button button-primary gatherpress-send-email__send">
			<?php esc_html_e( 'Send Email', 'gatherpress' ); ?>
		</button>
	</p>
</div>
