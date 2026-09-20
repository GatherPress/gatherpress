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
 *
 * `show_if` names another task whose checkbox controls whether this row is
 * shown, and the state it has to be in. The RSVP row uses it because removing
 * events removes the RSVPs on them either way.
 */
$gatherpress_tasks = array(
	Preferences::TASK_EVENTS  => array(
		'label'       => Utility::post_type_label( 'name', Event::POST_TYPE ),
		'checkbox'    => sprintf(
			/* translators: %s: Plural post type label (e.g. "Events"). */
			__( 'Remove %s', 'gatherpress' ),
			Utility::post_type_label( 'name', Event::POST_TYPE )
		),
		'description' => sprintf(
			/* translators: %s: Plural post type label (e.g. "Events"). */
			__( 'Removes all %s with their meta and revisions, the RSVPs recorded against them, and the table that holds their dates.', 'gatherpress' ),
			Utility::post_type_label( 'name', Event::POST_TYPE )
		),
	),
	Preferences::TASK_RSVPS   => array(
		'label'       => __( 'RSVPs', 'gatherpress' ),
		'checkbox'    => __( 'Remove RSVPs', 'gatherpress' ),
		'description' => __( 'Removes every RSVP record, the answers people gave to custom RSVP fields, and the internal terms that track each response.', 'gatherpress' ),
		'show_if'     => array( Preferences::TASK_EVENTS => false ),
	),
	Preferences::TASK_TOPICS  => array(
		'label'       => Utility::taxonomy_label( 'name', Topic::TAXONOMY ),
		'checkbox'    => sprintf(
			/* translators: %s: Plural taxonomy label (e.g. "Topics"). */
			__( 'Remove %s', 'gatherpress' ),
			Utility::taxonomy_label( 'name', Topic::TAXONOMY )
		),
		'description' => sprintf(
			/* translators: %s: Plural taxonomy label (e.g. "Topics"). */
			__( 'Removes all %s and the way they were assigned. They are kept by default, because a vocabulary you built can outlive the plugin.', 'gatherpress' ),
			Utility::taxonomy_label( 'name', Topic::TAXONOMY )
		),
	),
	Preferences::TASK_VENUES  => array(
		'label'       => Utility::post_type_label( 'name', Venue::POST_TYPE ),
		'checkbox'    => sprintf(
			/* translators: %s: Plural post type label (e.g. "Venues"). */
			__( 'Remove %s', 'gatherpress' ),
			Utility::post_type_label( 'name', Venue::POST_TYPE )
		),
		'description' => sprintf(
			/* translators: 1: Plural post type label (e.g. "Venues"), 2: Plural post type label (e.g. "Events"). */
			__( 'Removes all %1$s with their meta and revisions, and the internal terms GatherPress keeps to connect %2$s to them.', 'gatherpress' ),
			Utility::post_type_label( 'name', Venue::POST_TYPE ),
			Utility::post_type_label( 'name', Event::POST_TYPE )
		),
	),
	Preferences::TASK_FILES   => array(
		'label'       => __( 'Generated files', 'gatherpress' ),
		'checkbox'    => __( 'Remove generated files', 'gatherpress' ),
		'description' => sprintf(
			/* translators: %s: Plural post type label (e.g. "Venues"). */
			__( 'Deletes the files GatherPress generated in your uploads folder, such as the static maps for %s. They are not in your media library.', 'gatherpress' ),
			Utility::post_type_label( 'name', Venue::POST_TYPE )
		),
	),
	Preferences::TASK_CRON    => array(
		'label'       => __( 'Scheduled jobs', 'gatherpress' ),
		'checkbox'    => __( 'Remove scheduled jobs', 'gatherpress' ),
		'description' => __( 'Clears the scheduled tasks the plugin registers, such as RSVP cleanup and map generation.', 'gatherpress' ),
	),
	Preferences::TASK_USERS   => array(
		'label'       => __( 'User preferences', 'gatherpress' ),
		'checkbox'    => __( 'Remove user preferences', 'gatherpress' ),
		'description' => __( 'Removes what each person chose for themselves: their time zone and time format, whether they receive event updates, and their RSVP screen options.', 'gatherpress' ),
	),
	Preferences::TASK_OPTIONS => array(
		'label'       => __( 'Settings', 'gatherpress' ),
		'checkbox'    => __( 'Remove settings', 'gatherpress' ),
		'description' => __( 'Removes the GatherPress settings, including the choices on this screen.', 'gatherpress' ),
	),
);
?>

<h2><?php esc_html_e( 'Uninstall', 'gatherpress' ); ?></h2>

<p class="description">
	<?php esc_html_e( 'Choose what GatherPress removes when you delete the plugin. Everything is off by default, so your data stays unless you select it here.', 'gatherpress' ); ?>
</p>

<div class="notice notice-warning inline">
	<p>
		<strong><?php esc_html_e( 'These choices cannot be undone.', 'gatherpress' ); ?></strong>
		<?php esc_html_e( 'Deactivating removes nothing, and only a backup brings back what deleting takes. Export your settings from the Tools tab first, and your events and venues under Tools > Export.', 'gatherpress' ); ?>
	</p>
</div>

<p class="description">
	<?php
	printf(
		/* translators: %s: The WP-CLI command that deletes the plugin, in a code element. */
		esc_html__( 'On a large site, delete with WP-CLI: %s. A browser request can hit the web server time limit and stop part way through.', 'gatherpress' ),
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
					$gatherpress_show_if     = $gatherpress_task['show_if'] ?? array();
					$gatherpress_row_class   = Uninstall::row_class( $gatherpress_show_if, $gatherpress_preferences );
					?>
					<tr class="<?php echo esc_attr( $gatherpress_row_class ); ?>">
						<th scope="row"><?php echo esc_html( $gatherpress_task['label'] ); ?></th>
						<td>
							<?php if ( ! empty( $gatherpress_show_if ) ) : ?>
								<input
									type="hidden"
									class="gatherpress-show-if-marker"
									data-show-if="<?php echo esc_attr( (string) wp_json_encode( Uninstall::show_if_condition( $gatherpress_show_if ) ) ); ?>"
								/>
							<?php endif; ?>
							<label for="<?php echo esc_attr( $gatherpress_field_id ); ?>">
								<input
									type="checkbox"
									id="<?php echo esc_attr( $gatherpress_field_id ); ?>"
									name="<?php echo esc_attr( Uninstall::field_name( $gatherpress_key ) ); ?>"
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
