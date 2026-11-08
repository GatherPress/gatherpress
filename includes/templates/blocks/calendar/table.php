<?php
/**
 * Calendar Unit Table Template.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset(
	$table_class,
	$table_style,
	$caption,
	$show_weekdays,
	$day_names,
	$render_week_numbers,
	$is_time_grid,
	$weeks,
) ) {
	return;
}
?>
<table class="<?php echo esc_attr( $table_class ); ?>" style="<?php echo esc_attr( $table_style ); ?>">
	<?php if ( '' !== $caption ) { ?>
		<caption class="gatherpress--screen-reader-text"><?php echo esc_html( $caption ); ?></caption>
	<?php } ?>
	<?php // Without "Show Weekdays", the header row is only hidden on screen: screen readers still read the weekday of each cell. ?>
	<thead<?php echo $show_weekdays ? '' : ' class="gatherpress--screen-reader-text"'; ?>>
		<tr>
			<?php // Empty header for the time gutter column. ?>
			<?php if ( $is_time_grid ) { ?>
				<th scope="col" class="gatherpress-calendar__time-gutter-header" aria-hidden="true"></th>
			<?php } ?>
			<?php // Header for the hidden week number column, so the row headers line up. ?>
			<?php if ( $render_week_numbers ) { ?>
				<th scope="col" class="gatherpress--screen-reader-text"><?php echo esc_html_x( 'Week', 'week number column header', 'gatherpress' ); ?></th>
			<?php } ?>
			<?php foreach ( $day_names as $gatherpress_day_name ) { ?>
				<th scope="col"><?php echo esc_html( $gatherpress_day_name ); ?></th>
			<?php } ?>
		</tr>
	</thead>
	<tbody>
		<?php echo wp_kses_post( $weeks ); ?>
	</tbody>
</table>
