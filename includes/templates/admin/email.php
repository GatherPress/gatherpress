<?php
/**
 * Template for the GatherPress Email page.
 *
 * Provides a form for sending a message to every opted-in member of the site.
 *
 * @package GatherPress\Core
 * @since TBD
 *
 * @param int                   $recipient_count Number of members the message would go out to.
 * @param array<string, string> $audiences       Audience labels, keyed by audience.
 * @param array<string, string> $roles           Role names, keyed by role.
 * @param int[]                 $active_months   Choices for how far back an active member's last RSVP can be.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $recipient_count ) ) {
	return;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Email', 'gatherpress' ); ?></h1>

	<h2><?php esc_html_e( 'Send Email To Members', 'gatherpress' ); ?></h2>
	<p class="description">
		<?php
		esc_html_e(
			'Send a message to members of this site who have event updates turned on. Members can turn these emails off from their profile, and they are always skipped.',
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
					<label for="gatherpress-message-months"><?php esc_html_e( "RSVP'd in the past", 'gatherpress' ); ?></label>
				</div>
				<div class="gatherpress-settings-form__field">
					<select id="gatherpress-message-months">
						<?php foreach ( $active_months ?? array() as $gatherpress_index => $gatherpress_months ) : ?>
							<option value="<?php echo esc_attr( (string) $gatherpress_months ); ?>" <?php selected( 1, $gatherpress_index ); ?>>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: Number of months. */
										_n( '%d month', '%d months', $gatherpress_months, 'gatherpress' ),
										$gatherpress_months
									)
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( "Members who RSVP'd to any event in that time.", 'gatherpress' ); ?></p>
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
</div>
