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
use GatherPress\Core\Block;

$gatherpress_block_instance = Block::get_instance();
$gatherpress_post_id        = $gatherpress_block_instance->get_post_id( $block->parsed_block );
$gatherpress_event          = new Event( $gatherpress_post_id );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php
	echo esc_html(
		$gatherpress_event->get_display_datetime(
			$attributes['displayType'] ?? '',
			$attributes['startDateFormat'] ?? '',
			$attributes['endDateFormat'] ?? '',
			$attributes['separator'] ?? '',
			$attributes['showTimezone'] ?? ''
		)
	);
	?>
</div>
