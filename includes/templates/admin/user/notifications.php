<?php
/**
 * User Notifications Settings Template.
 *
 * @package GatherPress\Core
 * @since 0.28.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $event_updates_opt_in ) ) {
	return;
}

// Callers that do not pass a label fall back to the generic event wording.
$gatherpress_plural = isset( $plural_label ) && '' !== $plural_label ? $plural_label : __( 'events', 'gatherpress' );
?>

<h2 id="gatherpress-user-notifications">
	<?php esc_html_e( 'Notifications', 'gatherpress' ); ?>
</h2>
<table class="form-table" aria-describedby="gatherpress-user-notifications">
	<tr>
		<th scope="row"><?php esc_html_e( 'Email', 'gatherpress' ); ?></th>
		<td>
			<label for="gatherpress-event-updates-opt-in">
				<input
					name="gatherpress_event_updates_opt_in"
					type="checkbox"
					id="gatherpress-event-updates-opt-in"
					value="1"
					<?php checked( '1', $event_updates_opt_in ); ?>
				/>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: Plural name of the content type members hear about. */
						__( 'Yes, I want to receive updates and information about %1$s from the organizers.', 'gatherpress' ),
						$gatherpress_plural
					)
				);
				?>
			</label>
		</td>
	</tr>
</table>
