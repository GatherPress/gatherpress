<?php
/**
 * User Notifications Settings Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! isset( $event_updates_opt_in ) ) {
	return;
}
?>

<h2 id="gp-user-notifications">
	<?php esc_html_e( 'Notifications', 'gatherpress' ); ?>
</h2>
<table class="form-table" aria-describedby="gp-user-notifications">
	<tr>
		<th scope="row"><?php esc_html_e( 'Email', 'gatherpress' ); ?></th>
		<td>
			<label for="gp-event-updates-opt-in">
				<input
					name="gp_event_updates_opt_in"
					type="checkbox"
					id="gp-event-updates-opt-in"
					value="1"
					<?php checked( '1', $event_updates_opt_in ); ?>
				/>
				<?php esc_html_e( 'Yes, I want to receive updates and information about events from the organizers.', 'gatherpress' ); ?>
			</label>
		</td>
	</tr>
</table>
