<?php
/**
 * Render Event Doors block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Doors;

$gatherpress_post_id = Setup::get_instance()->get_post_id( $block->parsed_block );

// Same gate as the Event Date block, since the doors give away when the event is.
if (
	! post_type_supports( (string) get_post_type( $gatherpress_post_id ), Event::SUPPORT )
	|| ! Event::is_viewable( $gatherpress_post_id )
	|| post_password_required( $gatherpress_post_id )
) {
	return;
}

$gatherpress_doors  = new Doors( new Event( $gatherpress_post_id ) );
$gatherpress_parts  = array();
$gatherpress_labels = array(
	'open'  => __( 'Doors open', 'gatherpress' ),
	'close' => __( 'Doors close', 'gatherpress' ),
);

foreach ( $gatherpress_labels as $gatherpress_which => $gatherpress_label ) {
	$gatherpress_datetime = $gatherpress_doors->get_datetime( $gatherpress_which );

	if ( null === $gatherpress_datetime ) {
		continue;
	}

	$gatherpress_parts[] = sprintf(
		'<span class="gatherpress-event-doors__%1$s">%2$s <time datetime="%3$s">%4$s</time></span>',
		esc_attr( $gatherpress_which ),
		esc_html( $gatherpress_label ),
		esc_attr( $gatherpress_datetime->format( 'c' ) ),
		esc_html( $gatherpress_doors->get_time( $gatherpress_which ) )
	);
}

if ( empty( $gatherpress_parts ) ) {
	return;
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php
	echo wp_kses(
		implode( ' ', $gatherpress_parts ),
		array(
			'span' => array( 'class' => true ),
			'time' => array( 'datetime' => true ),
		)
	);
	?>
</div>
