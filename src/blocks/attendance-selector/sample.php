<?php

echo '<p>PHP callback for <span  style="color:maroon;">' . __FILE__ . '</span></p>';
if ( 'gp_event' === get_post_type( get_the_ID() ) ) {
    $gatherpress_event = new \GatherPress\Includes\Event( get_the_ID() );
	echo '<p class="">' . $gatherpress_event->get_display_datetime() . '</p>';
}
