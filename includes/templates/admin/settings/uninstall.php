<?php
/**
 * Template for the GatherPress uninstall settings page.
 *
 * Declares what deleting the plugin is allowed to remove.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

use GatherPress\Core\Event;
use GatherPress\Core\Settings\Uninstall;
use GatherPress\Core\Topic;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

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
		'label'       => sprintf(
			/* translators: 1: Plural post type label (e.g. "Events"), 2: Plural post type label (e.g. "Venues"). */
			__( '%1$s and %2$s', 'gatherpress' ),
			Utility::post_type_label( 'name', Event::POST_TYPE ),
			Utility::post_type_label( 'name', Venue::POST_TYPE )
		),
		'checkbox'    => sprintf(
			/* translators: 1: Plural post type label (e.g. "Events"), 2: Plural post type label (e.g. "Venues"). */
			__( 'Remove %1$s and %2$s', 'gatherpress' ),
			Utility::post_type_label( 'name', Event::POST_TYPE ),
			Utility::post_type_label( 'name', Venue::POST_TYPE )
		),
		'description' => sprintf(
			/* translators: 1: Plural post type label (e.g. "Events"), 2: Plural post type label (e.g. "Venues"). */
			__( 'Removes all %1$s and %2$s, with their meta, revisions, and the RSVPs recorded against them.', 'gatherpress' ),
			Utility::post_type_label( 'name', Event::POST_TYPE ),
			Utility::post_type_label( 'name', Venue::POST_TYPE )
		),
	),
	Preferences::TASK_COMMENTS => array(
		'label'       => __( 'RSVPs', 'gatherpress' ),
		'checkbox'    => __( 'Remove RSVPs', 'gatherpress' ),
		'description' => __( 'Removes every RSVP record and the answers people gave to custom RSVP fields.', 'gatherpress' ),
	),
	Preferences::TASK_TERMS    => array(
		'label'       => Utility::taxonomy_label( 'name', Topic::TAXONOMY ),
		'checkbox'    => sprintf(
			/* translators: %s: Plural taxonomy label (e.g. "Topics"). */
			__( 'Remove %s', 'gatherpress' ),
			Utility::taxonomy_label( 'name', Topic::TAXONOMY )
		),
		'description' => sprintf(
			/* translators: 1: Plural taxonomy label (e.g. "Topics"), 2: Plural post type label (e.g. "Venues"). */
			__( 'Removes all %1$s and the internal terms GatherPress keeps for %2$s and RSVP records. Categories and tags are not touched.', 'gatherpress' ),
			Utility::taxonomy_label( 'name', Topic::TAXONOMY ),
			Utility::post_type_label( 'name', Venue::POST_TYPE )
		),
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

<p class="description">
	<?php
	printf(
		/* translators: %s: The WP-CLI command that deletes the plugin, in a code element. */
		esc_html__( 'On a large site, delete the plugin with WP-CLI: %s. A browser request can stop at the web server time limit and leave part of the data behind. WP-CLI has no such limit.', 'gatherpress' ),
		'<code>wp plugin uninstall gatherpress --deactivate</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup, no user input.
	);
	?>
</p>

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
