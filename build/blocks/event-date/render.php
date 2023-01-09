<?php
/**
 * Render Event Date block.
 *
 * @package    GatherPress
 * @subpackage Core
 * @since      1.0.0
 */

use GatherPress\Core\Event;

$gatherpress_event = new Event(get_the_ID());
?>
<div <?php echo wp_kses_data(get_block_wrapper_attributes()); ?>>
    <div class="gp-event-date__row">
        <div class="gp-event-date__item">
            <div class="gp-event-date__icon">
                <div class="dashicons dashicons-clock"></div>
            </div>
            <div class="gp-event-date__text">
                <?php echo esc_html($gatherpress_event->get_display_datetime()); ?>
            </div>
        </div>
    </div>
</div>
