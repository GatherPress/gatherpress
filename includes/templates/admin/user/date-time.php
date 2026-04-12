<?php
/**
 * User Date Time Settings Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\User;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $time_format, $timezone, $tz_choices ) ) {
	return;
}
?>
<div style="margin-top: 40px; margin-bottom: 30px;">
	<h2 id="gatherpress-user-time-formatting">
		<?php esc_html_e( 'Time Display Formatting', 'gatherpress' ); ?>
	</h2>
	<table class="form-table" aria-describedby="gatherpress-date-time-formatting">
		<tr>
			<th><label for="gatherpress_time_format"><?php esc_html_e( 'Time Format', 'gatherpress' ); ?></label></th>
			<td>
				<div class="form-wrap">
					<select name="gatherpress_time_format" id="gatherpress_time_format">
						<option value="">
							<?php esc_html_e( 'Default', 'gatherpress' ); ?>
						</option>
						<option value="<?php echo esc_attr( User::HOUR_12 ); ?>" <?php selected( User::HOUR_12, $time_format ); ?>>
							<?php esc_html_e( '12-hour', 'gatherpress' ); ?>
						</option>
						<option value="<?php echo esc_attr( User::HOUR_24 ); ?>" <?php selected( User::HOUR_24, $time_format ); ?>>
							<?php esc_html_e( '24-hour', 'gatherpress' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Override the site default time format for event times displayed to you.', 'gatherpress' ); ?></p>
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="gatherpress_timezone"><?php esc_html_e( 'Timezone', 'gatherpress' ); ?></label></th>
			<td>
				<div class="form-wrap">
					<select name="gatherpress_timezone" id="gatherpress_timezone">
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
					<p class="description"><?php esc_html_e( 'Set your timezone to see event times in your local time.', 'gatherpress' ); ?></p>
				</div>
			</td>
		</tr>
	</table>
</div>
