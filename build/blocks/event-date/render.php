<?php
/**
 * Render Event Date block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

$gatherpress_event = new Event( get_the_ID() );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php echo esc_html( $gatherpress_event->get_display_datetime() ); ?>
</div>
