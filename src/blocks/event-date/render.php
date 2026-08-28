<?php
/**
 * Render Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 0.27.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Setup;
use GatherPress\Core\Event;

$gatherpress_block_instance = Setup::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );
$gatherpress_event          = new Event( $gatherpress_post_id );

$gatherpress_parts = $gatherpress_event->get_display_datetime_parts(
	$attributes['displayType'] ?? '',
	$attributes['startDateFormat'] ?? '',
	$attributes['endDateFormat'] ?? '',
	$attributes['separator'] ?? '',
	$attributes['showTimezone'] ?? ''
);

$gatherpress_render_part = static function ( $human, $iso ): string {
	if ( empty( $human ) ) {
		return '';
	}

	return empty( $iso )
		? esc_html( $human )
		: sprintf( '<time datetime="%s">%s</time>', esc_attr( $iso ), esc_html( $human ) );
};

$gatherpress_output_parts = array_filter(
	array(
		$gatherpress_render_part( $gatherpress_parts['start'], $gatherpress_event->get_datetime_start_iso() ),
		esc_html( $gatherpress_parts['separator'] ),
		$gatherpress_render_part( $gatherpress_parts['end'], $gatherpress_event->get_datetime_end_iso() ),
		esc_html( $gatherpress_parts['timezone'] ),
	)
);

$gatherpress_display = $gatherpress_output_parts
	? implode( ' ', $gatherpress_output_parts )
	: esc_html( Event::DATETIME_PLACEHOLDER );

if ( ! empty( $attributes['isLink'] ) ) {
	$gatherpress_display = sprintf(
		'<a href="%s">%s</a>',
		esc_url( get_permalink( $gatherpress_post_id ) ),
		$gatherpress_display
	);
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php
	echo wp_kses(
		$gatherpress_display,
		array(
			'a'    => array( 'href' => true ),
			'time' => array( 'datetime' => true ),
		)
	);
	?>
</div>
