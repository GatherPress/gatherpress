<?php
/**
 * Template for the GatherPress uninstall settings page.
 *
 * Declares what deleting the plugin is allowed to remove.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

use GatherPress\Core\Settings\Uninstall;
use GatherPress\Core\Uninstall\Preferences;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_preferences = isset( $preferences ) && is_array( $preferences ) ? $preferences : array();
$gatherpress_can_edit    = ! empty( $can_edit );
$gatherpress_scope       = isset( $scope ) && 'network' === $scope ? 'network' : 'blog';

/*
 * `label` names the row, the way core's settings screens do. `checkbox` is the
 * control's own label, and says what it does without the row heading: the table
 * is `role="presentation"`, so the heading is not read out with the control.
 */
$gatherpress_tasks = array(
	Preferences::TASK_POSTS    => array(
		'label'       => __( 'Events and venues', 'gatherpress' ),
		'checkbox'    => __( 'Remove events and venues', 'gatherpress' ),
		'description' => __( 'Removes every event and venue, with their meta, revisions, and the RSVPs recorded against them.', 'gatherpress' ),
	),
	Preferences::TASK_COMMENTS => array(
		'label'       => __( 'RSVPs', 'gatherpress' ),
		'checkbox'    => __( 'Remove RSVPs', 'gatherpress' ),
		'description' => __( 'Removes every RSVP record and the answers people gave to custom RSVP fields.', 'gatherpress' ),
	),
	Preferences::TASK_TERMS    => array(
		'label'       => __( 'Topics and venue terms', 'gatherpress' ),
		'checkbox'    => __( 'Remove topics and venue terms', 'gatherpress' ),
		'description' => __( 'Removes the terms in the topic, venue, and RSVP taxonomies. Categories and tags are not touched.', 'gatherpress' ),
	),
	Preferences::TASK_TABLES   => array(
		'label'       => __( 'Event date table', 'gatherpress' ),
		'checkbox'    => __( 'Remove the event date table', 'gatherpress' ),
		'description' => __( 'Drops the custom database table that holds event start and end times.', 'gatherpress' ),
	),
	Preferences::TASK_CRON     => array(
		'label'       => __( 'Scheduled jobs', 'gatherpress' ),
		'checkbox'    => __( 'Remove scheduled jobs', 'gatherpress' ),
		'description' => __( 'Clears the scheduled tasks the plugin registers, such as RSVP cleanup and map generation.', 'gatherpress' ),
	),
	Preferences::TASK_OPTIONS  => array(
		'label'       => __( 'Settings', 'gatherpress' ),
		'checkbox'    => __( 'Remove settings', 'gatherpress' ),
		'description' => __( 'Removes the GatherPress settings, including the choices on this screen.', 'gatherpress' ),
	),
);
?>

<h2><?php esc_html_e( 'Uninstall', 'gatherpress' ); ?></h2>

<p class="description">
	<?php esc_html_e( 'Choose what GatherPress removes when the plugin is deleted from the Plugins screen. Everything is off by default, so deleting the plugin keeps your data unless you ask for it to go.', 'gatherpress' ); ?>
</p>

<div class="notice notice-warning inline">
	<p>
		<strong><?php esc_html_e( 'These choices cannot be undone.', 'gatherpress' ); ?></strong>
		<?php esc_html_e( 'Deactivating the plugin removes nothing. The data goes only when the plugin is deleted, and it cannot be recovered without a backup.', 'gatherpress' ); ?>
	</p>
</div>

<?php if ( ! $gatherpress_can_edit ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php
			if ( is_multisite() ) {
				esc_html_e( 'These choices apply to the whole network, so only a network administrator can change them.', 'gatherpress' );
			} else {
				esc_html_e( 'You do not have permission to change these choices.', 'gatherpress' );
			}
			?>
		</p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Uninstall::SAVE_ACTION ); ?>" />
	<input type="hidden" name="gatherpress_scope" value="<?php echo esc_attr( $gatherpress_scope ); ?>" />
	<?php wp_nonce_field( Uninstall::SAVE_ACTION, Uninstall::NONCE_NAME ); ?>

	<fieldset<?php echo $gatherpress_can_edit ? '' : ' disabled'; ?>>
		<legend class="screen-reader-text">
			<?php esc_html_e( 'Data to remove when the plugin is deleted', 'gatherpress' ); ?>
		</legend>

		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $gatherpress_tasks as $gatherpress_key => $gatherpress_task ) : ?>
					<?php
					$gatherpress_field_id    = 'gatherpress-uninstall-' . $gatherpress_key;
					$gatherpress_describedby = $gatherpress_field_id . '-description';
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $gatherpress_task['label'] ); ?></th>
						<td>
							<label for="<?php echo esc_attr( $gatherpress_field_id ); ?>">
								<input
									type="checkbox"
									id="<?php echo esc_attr( $gatherpress_field_id ); ?>"
									name="<?php echo esc_attr( 'gatherpress_uninstall_' . $gatherpress_key ); ?>"
									value="1"
									aria-describedby="<?php echo esc_attr( $gatherpress_describedby ); ?>"
									<?php checked( ! empty( $gatherpress_preferences[ $gatherpress_key ] ) ); ?>
								/>
								<?php echo esc_html( $gatherpress_task['checkbox'] ); ?>
							</label>
							<p class="description" id="<?php echo esc_attr( $gatherpress_describedby ); ?>">
								<?php echo esc_html( $gatherpress_task['description'] ); ?>
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</fieldset>

	<?php if ( $gatherpress_can_edit ) : ?>
		<?php submit_button( __( 'Save Uninstall Settings', 'gatherpress' ) ); ?>
	<?php endif; ?>
</form>
