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

$gatherpress_nonce = wp_create_nonce( 'gatherpress_send_email_nonce' );
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

<style>
	.gatherpress-settings-form {
		margin-top: 1em;
	}
	.gatherpress-settings-form__row {
		display: grid;
		grid-template-columns: 200px 1fr;
		gap: 0 10px;
		padding: 15px 10px;
	}
	.gatherpress-settings-form__label {
		font-weight: 600;
		padding-top: 2px;
	}
	.gatherpress-settings-form__row--full > * {
		grid-column: 1 / -1;
	}
	@media screen and (max-width: 782px) {
		.gatherpress-settings-form__row {
			grid-template-columns: 1fr;
			gap: 6px;
		}
	}
</style>

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
				placeholder="<?php esc_attr_e( 'Leave blank to use the site name', 'gatherpress' ); ?>"
			/>
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

<output id="gatherpress-message-result" class="gatherpress-saving" style="display: none; align-items: center; gap: 8px;">
	<span class="spinner is-active" style="float: none;"></span>
	<span id="gatherpress-message-result-text" style="font-weight: bold;"></span>
</output>

<p>
	<button id="gatherpress-send-message" class="button button-primary">
		<?php esc_html_e( 'Send Email', 'gatherpress' ); ?>
	</button>
</p>

<script>
(function() {
	const nonce = '<?php echo esc_js( $gatherpress_nonce ); ?>';
	const button = document.getElementById('gatherpress-send-message');
	const subjectField = document.getElementById('gatherpress-message-subject');
	const messageField = document.getElementById('gatherpress-message-body');
	const result = document.getElementById('gatherpress-message-result');
	const resultText = document.getElementById('gatherpress-message-result-text');
	const spinner = result.querySelector('.spinner');

	button.addEventListener('click', function(e) {
		e.preventDefault();

		if (!messageField.value.trim()) {
			alert('<?php echo esc_js( __( 'Please write a message before sending.', 'gatherpress' ) ); ?>');
			return;
		}

		if (!window.confirm('<?php echo esc_js( __( 'Send this message to every member? This cannot be undone.', 'gatherpress' ) ); ?>')) {
			return;
		}

		const data = new URLSearchParams({
			action: 'gatherpress_send_site_message',
			nonce: nonce,
			subject: subjectField.value,
			message: messageField.value
		});

		button.disabled = true;
		result.style.display = 'flex';
		resultText.textContent = '<?php echo esc_js( __( 'Queuing message...', 'gatherpress' ) ); ?>';
		spinner.classList.add('is-active');

		fetch(window.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data
		})
		.then(function(response) { return response.json(); })
		.then(function(response) {
			if (response.success) {
				resultText.textContent = response.data.message;
				messageField.value = '';
				return;
			}

			resultText.textContent = response.data && response.data.message
				? response.data.message
				: '<?php echo esc_js( __( 'The message could not be queued.', 'gatherpress' ) ); ?>';
		})
		.catch(function() {
			// A network or JSON failure still needs to report back to the sender.
			resultText.textContent = '<?php echo esc_js( __( 'The message could not be queued.', 'gatherpress' ) ); ?>';
		})
		.finally(function() {
			button.disabled = false;
			spinner.classList.remove('is-active');
		});
	});
})();
</script>
