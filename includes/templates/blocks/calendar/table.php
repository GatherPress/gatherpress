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
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_table_style() escapes it. ?>
<table class="<?php echo esc_attr( $table_class ); ?>" <?php echo $table_style; ?>>
	<?php if ( '' !== $caption ) { ?>
		<caption class="gatherpress--screen-reader-text"><?php echo esc_html( $caption ); ?></caption>
	<?php } ?>
	<?php // Without "Show Weekdays", the header row is only hidden on screen: screen readers still read the weekday of each cell. ?>
	<thead<?php echo $show_weekdays ? '' : ' class="gatherpress--screen-reader-text"'; ?>>
		<tr>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_header_cells() escapes it.
			echo $header_cells;
			?>
		</tr>
	</thead>
	<tbody>
		<?php
		// Rendered blocks escape their own output. wp_kses_post() here would remove
		// aria-modal from modals, and SVG, form and iframe markup.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $weeks;
		?>
	</tbody>
</table>
