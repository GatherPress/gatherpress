<?php
/**
 * User Date Time Settings Template.
 *
 * @package GatherPress\Core
 * @since 0.29.0
 */

use GatherPress\Core\Utility;

if ( ! isset( $timezone ) && ! isset( $date_format ) && ! isset( $time_format ) ) {
	return;
}

$tz_choices = Utility::timezone_choices();

$date_attrs = array(
	'name'  => 'gp_date_format',
	'value' => ! empty( $date_format ) ? $date_format : '',
);

$time_attrs = array(
	'name'  => 'gp_time_format',
	'value' => ! empty( $time_format ) ? $time_format : '',
);
?>
<div style="margin-top: 40px; margin-bottom: 30px;">
	<h2 id="gp-user-date-time">
		<?php esc_html_e( 'Date & Time Formatting', 'gatherpress' ); ?>
	</h2>
	<div>
		<?php _e( 'For more information read the <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/">Documentation on date and time formatting</a>.', 'gatherpress' ); ?>
	</div>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="gp_date_format"><?php _e( 'Date Format', 'gatherpress' ); ?></label></th>
			<td>
				<div class="form-wrap">
					<label for="gp_date_format"><?php _e('Format of date for scheduled events.', 'gatherpress'); ?></label>
					<input type="text" name="gp_date_format" id="gp_date_format" value="<?php echo esc_attr( $date_format ); ?>" />
					<p>
						<strong><?php _e( 'Preview', 'gatherpress' ); ?>:</strong>
						<span data-gp_component_name="datetime-preview" data-gp_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $date_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></span>
					</p>
				</div>
			</td>
		</tr>
		<tr>
			<th>
				<label for="gp_time_format"><?php _e( 'Time Format', 'gatherpress' ); ?></label></th>
			<td>
				<div class="form-wrap">
					<label for="gp_date_format"><?php _e('Format of time for scheduled events.', 'gatherpress'); ?></label>
					<input type="text" name="gp_time_format" id="gp_time_format" value="<?php echo esc_attr( $time_format ); ?>" />
					<p>
						<strong><?php _e( 'Preview', 'gatherpress' ); ?>:</strong>
						<span data-gp_component_name="datetime-preview" data-gp_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $time_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></span>
					</p>
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="gp_timezone"><?php _e( 'Timezone', 'gatherpress' ); ?></label></th>
			<td>
				<select name="gp_timezone">
					<option value="">--</option>
					<?php
					foreach( $tz_choices as $location => $timezones ) {
						echo '<optgroup label="' . $location . '">';
						foreach( $timezones as $tz => $name ) {
							echo '<option value="' . $tz . '"';
							if ($timezone === $tz) {
								echo ' selected';
							}
							echo '>' . $name . '</option>';
						}
						echo '</optgroup>';
					}
					?>
				</select>
			</td>
		</tr>
	</table>
</div>
