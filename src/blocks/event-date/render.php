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
use GatherPress\Core\Event\Status;

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

$gatherpress_render_part = static function ( string $human, string $iso ): string {
	if ( empty( $human ) ) {
		return '';
	}

	return empty( $iso )
		? esc_html( $human )
		: sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( $iso ),
			esc_html( $human )
		);
};

$gatherpress_output_parts = array_filter(
	array(
		$gatherpress_render_part(
			(string) $gatherpress_parts['start'],
			$gatherpress_event->get_datetime_start_iso()
		),
		esc_html( (string) $gatherpress_parts['separator'] ),
		$gatherpress_render_part(
			(string) $gatherpress_parts['end'],
			$gatherpress_event->get_datetime_end_iso()
		),
		esc_html( (string) $gatherpress_parts['timezone'] ),
	)
);

$gatherpress_display = $gatherpress_output_parts
	? implode( ' ', $gatherpress_output_parts )
	: Event::DATETIME_PLACEHOLDER;

if ( ! empty( $attributes['isLink'] ) ) {
	$gatherpress_display = sprintf(
		'<a href="%s">%s</a>',
		esc_url( get_permalink( $gatherpress_post_id ) ),
		$gatherpress_display
	);
}

$gatherpress_status       = $gatherpress_event->get_status();
$gatherpress_status_label = '';
$gatherpress_classes      = array();
if ( Status::default_slug( (string) get_post_type( $gatherpress_post_id ) ) !== $gatherpress_status ) {
	$gatherpress_classes[]    = sprintf( 'gatherpress-event-date--is-%s', sanitize_html_class( $gatherpress_status ) );
	$gatherpress_status_label = Status::label( $gatherpress_status );
}

$gatherpress_wrapper_attributes = empty( $gatherpress_classes )
	? get_block_wrapper_attributes()
	: get_block_wrapper_attributes( array( 'class' => implode( ' ', $gatherpress_classes ) ) );
?>
<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<?php
	echo wp_kses(
		$gatherpress_display,
		array(
			'a'    => array( 'href' => true ),
			'time' => array( 'datetime' => true ),
		)
	);
	?>
	<?php if ( '' !== $gatherpress_status_label ) : ?>
		<span class="screen-reader-text gatherpress--screen-reader-text"><?php echo esc_html( sprintf( ' (%s)', $gatherpress_status_label ) ); ?></span>
	<?php endif; ?>
</div>
