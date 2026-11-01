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
	$header_cells,
	$weeks,
) ) {
	return;
}
?>
<?php // The roles repeat the native ones, so Safari keeps the table semantics with the grid and display: contents layout. ?>
<table role="table" class="<?php echo esc_attr( $table_class ); ?>" style="<?php echo esc_attr( $table_style ); ?>">
	<?php if ( '' !== $caption ) { ?>
		<caption class="gatherpress--screen-reader-text"><?php echo esc_html( $caption ); ?></caption>
	<?php } ?>
	<?php // Without "Show Weekdays", the header row is only hidden on screen: screen readers still read the weekday of each cell. ?>
	<thead role="rowgroup"<?php echo $show_weekdays ? '' : ' class="gatherpress--screen-reader-text"'; ?>>
		<tr role="row">
			<?php echo wp_kses_post( $header_cells ); ?>
		</tr>
	</thead>
	<tbody role="rowgroup">
		<?php echo wp_kses_post( $weeks ); ?>
	</tbody>
</table>
