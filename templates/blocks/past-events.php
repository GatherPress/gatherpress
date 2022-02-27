<?php
/**
 * Past events block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_max_posts = ( isset( $attrs ) && is_array( $attrs ) && ! empty( $attrs['maxNumberOfEvents'] ) ) ? intval( $attrs['maxNumberOfEvents'] ) : 5;
$gatherpress_max_posts = ( 0 > $gatherpress_max_posts ) ? 5 : $gatherpress_max_posts;
?>
<div id="gp-past-events-container" data-max_posts="<?php echo intval( $gatherpress_max_posts ); ?>"></div>
