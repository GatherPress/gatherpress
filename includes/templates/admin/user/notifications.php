<?php
/**
 * User Notifications Settings Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $event_updates_opt_in ) ) {
	return;
}
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
				<?php esc_html_e( 'Yes, I want to receive updates and information about events from the organizers.', 'gatherpress' ); ?>
			</label>
		</td>
	</tr>
</table>
