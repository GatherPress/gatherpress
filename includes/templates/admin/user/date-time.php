<?php
/**
 * User Date Time Settings Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $time_format, $timezone, $tz_choices ) ) {
	return;
}
?>
<div style="margin-top: 40px; margin-bottom: 30px;">
	<h2 id="gatherpress-user-date-time">
		<?php esc_html_e( 'Date & Time Formatting', 'gatherpress' ); ?>
	</h2>
	<div>
		<?php
		echo wp_kses(
			__( 'For more information read the <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/" target="_blank">Documentation on date and time formatting</a>.', 'gatherpress' ),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
				),
			)
		);
		?>
	</div>
	<table class="form-table" aria-describedby="gatherpress-user-date-time">
		<tr>
			<th><label for="gatherpress_time_format"><?php esc_html_e( 'Time Format', 'gatherpress' ) ?></label></th>
			<td>
				<div class="form-wrap">
					<label for="gatherpress_time_format"><?php esc_html_e( 'Format of time for scheduled events.', 'gatherpress' ); ?></label>
					<select name="gatherpress_time_format" id="gatherpress_time_format">
						<option value="">
							<?php esc_html_e( 'Default', 'gatherpress' ); ?>
						</option>
						<option value="12-hour" <?php selected( '12-hour', $time_format ); ?>>
							<?php esc_html_e( '12-hour', 'gatherpress' ); ?>
						</option>
						<option value="24-hour" <?php selected( '24-hour', $time_format ); ?>>
							<?php esc_html_e( '24-hour', 'gatherpress' ); ?>
						</option>
					</select>
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="gatherpress_timezone"><?php esc_html_e( 'Timezone', 'gatherpress' ); ?></label></th>
			<td>
				<select name="gatherpress_timezone">
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
