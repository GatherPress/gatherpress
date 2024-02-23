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
?>
<div style="margin-top: 40px; margin-bottom: 30px;">
	<h2 id="gp-user-date-time">
		<?php esc_html_e( 'Date & Time Formatting', 'gatherpress' ); ?>
	</h2>
	<div>
		<?php echo __( wp_kses( 'For more information read the <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/" target="_blank">Documentation on date and time formatting</a>.', array( 'a' => array( 'href' => array(), 'target' => array() ) ) ), 'gatherpress' ); ?>
	</div>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="gp_date_format"><?php esc_html_e( 'Date Format', 'gatherpress' ); ?></label></th>
			<td>
				<div class="form-wrap">
					<label for="gp_date_format"><?php esc_html_e( 'Format of date for scheduled events.', 'gatherpress' ); ?></label>
					<input type="text" name="gp_date_format" id="gp_date_format" value="<?php echo esc_attr( $date_format ); ?>" />
					<p>
						<strong><?php esc_html_e( 'Preview', 'gatherpress' ); ?>:</strong>
						<span data-gp_component_name="datetime-preview" data-gp_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $date_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></span>
					</p>
				</div>
			</td>
		</tr>
		<tr>
			<th>
				<label for="gp_time_format"><?php esc_html_e( 'Time Format', 'gatherpress' ); ?></label></th>
			<td>
				<div class="form-wrap">
					<label for="gp_date_format"><?php esc_html_e( 'Format of time for scheduled events.', 'gatherpress' ); ?></label>
					<input type="text" name="gp_time_format" id="gp_time_format" value="<?php echo esc_attr( $time_format ); ?>" />
					<p>
						<strong><?php esc_html_e( 'Preview', 'gatherpress' ); ?>:</strong>
						<span data-gp_component_name="datetime-preview" data-gp_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $time_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></span>
					</p>
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="gp_timezone"><?php esc_html_e( 'Timezone', 'gatherpress' ); ?></label></th>
			<td>
				<select name="gp_timezone">
					<option value="">--</option>
					<?php
					foreach ( $tz_choices as $gatherpress_location => $gatherpress_timezones ) {
						echo wp_kses( '<optgroup label="' . $gatherpress_location . '">', array( 'optgroup' => array( 'label' => array() ) ) );

						foreach ( $gatherpress_timezones as $gatherpress_tz => $gatherpress_name ) {
							echo '<option value="' . esc_attr( $gatherpress_tz ) . '"'
							. selected( $timezone, $gatherpress_tz, false ) . '>'
							. esc_html( $gatherpress_name ) . '</option>';
						}

						echo wp_kses( '</optgroup>', array( 'optgroup' => array() ) );
					}
					?>
				</select>
			</td>
		</tr>
	</table>
</div>
