<?php
/**
 * Template for the GatherPress Email page.
 *
 * Provides a form for sending a message to the site's opted-in users.
 *
 * @package GatherPress\Core
 * @since TBD
 *
 * @param int                   $recipient_count Number of users the message would go out to.
 * @param array<string, string> $audiences       Audience labels, keyed by audience.
 * @param array<string, string> $roles           Role names, keyed by role.
 * @param int[]                 $active_days     Choices, in days, for how far back an active user's last RSVP can be.
 * @param int                   $default_days    The choice picked by default.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $recipient_count ) ) {
	return;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Email', 'gatherpress' ); ?></h1>

	<h2><?php esc_html_e( 'Send Email To Users', 'gatherpress' ); ?></h2>
	<p class="description">
		<?php
		esc_html_e(
			'Send a message to users of this site who have event updates turned on. Users can turn these emails off from their profile, and they are always skipped.',
			'gatherpress'
		);
		?>
	</p>

	<div class="gatherpress-send-email" data-gatherpress_component_name="send-email">
		<div class="gatherpress-settings-form">
			<div class="gatherpress-settings-form__row">
				<div class="gatherpress-settings-form__label">
					<label for="gatherpress-message-audience"><?php esc_html_e( 'Send this message to', 'gatherpress' ); ?></label>
				</div>
				<div class="gatherpress-settings-form__field">
					<select id="gatherpress-message-audience" aria-describedby="gatherpress-message-count">
						<?php foreach ( $audiences ?? array() as $gatherpress_audience => $gatherpress_audience_label ) : ?>
							<option value="<?php echo esc_attr( $gatherpress_audience ); ?>"><?php echo esc_html( $gatherpress_audience_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<output id="gatherpress-message-count" class="gatherpress-send-email__count">
							<?php
							printf(
								esc_html(
									/* translators: %d: Number of users the message will be sent to. */
									_n(
										'This will email %d user.',
										'This will email %d users.',
										$recipient_count,
										'gatherpress'
									)
								),
								(int) $recipient_count
							);
							?>
						</output>
					</p>
				</div>
			</div>
			<div class="gatherpress-settings-form__row gatherpress--is-hidden" data-gatherpress_audience="role">
				<div class="gatherpress-settings-form__label">
					<span id="gatherpress-message-roles-label"><?php esc_html_e( 'Roles', 'gatherpress' ); ?></span>
				</div>
				<div class="gatherpress-settings-form__field">
					<p class="gatherpress-send-email__role-actions">
						<button type="button" class="button-link" data-gatherpress_roles="all">
							<?php esc_html_e( 'Select all', 'gatherpress' ); ?>
						</button>
						<span aria-hidden="true">|</span>
						<button type="button" class="button-link" data-gatherpress_roles="none">
							<?php esc_html_e( 'Deselect all', 'gatherpress' ); ?>
						</button>
					</p>
					<fieldset aria-labelledby="gatherpress-message-roles-label">
						<?php foreach ( $roles ?? array() as $gatherpress_role => $gatherpress_role_name ) : ?>
							<label>
								<input type="checkbox" name="gatherpress-message-roles" value="<?php echo esc_attr( $gatherpress_role ); ?>" />
								<?php echo esc_html( $gatherpress_role_name ); ?>
							</label>
							<br />
						<?php endforeach; ?>
					</fieldset>
				</div>
			</div>
			<?php if ( isset( $audiences['active'] ) ) : ?>
			<div class="gatherpress-settings-form__row gatherpress--is-hidden" data-gatherpress_audience="active">
				<div class="gatherpress-settings-form__label">
					<label for="gatherpress-message-days"><?php esc_html_e( "RSVP'd in the past", 'gatherpress' ); ?></label>
				</div>
				<div class="gatherpress-settings-form__field">
					<select id="gatherpress-message-days">
						<?php foreach ( $active_days ?? array() as $gatherpress_days ) : ?>
							<option value="<?php echo esc_attr( (string) $gatherpress_days ); ?>" <?php selected( $default_days ?? 0, $gatherpress_days ); ?>>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: Number of days. */
										_n( '%d day', '%d days', $gatherpress_days, 'gatherpress' ),
										$gatherpress_days
									)
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( "Users who RSVP'd to any event in that time.", 'gatherpress' ); ?></p>
				</div>
			</div>
			<?php endif; ?>
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
					<label for="gatherpress-message-body">
						<?php esc_html_e( 'Message', 'gatherpress' ); ?>
						<span class="description"><?php esc_html_e( '(required)', 'gatherpress' ); ?></span>
					</label>
				</div>
				<div class="gatherpress-settings-form__field">
					<textarea
						id="gatherpress-message-body"
						class="large-text"
						rows="8"
						aria-required="true"
					></textarea>
				</div>
			</div>
		</div>

		<output class="gatherpress-send-email__status">
			<span class="spinner"></span>
			<span class="gatherpress-send-email__status-text"></span>
		</output>

		<p>
			<button type="button" class="button button-primary gatherpress-send-email__send">
				<?php esc_html_e( 'Send Email', 'gatherpress' ); ?>
			</button>
		</p>
	</div>
</div>
