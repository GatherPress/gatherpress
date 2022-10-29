<?php

echo '<h4>PHP callback for <span  style="color:maroon;">' . __DIR__ . '</span></h4>';
if ( 'gp_event' === get_post_type( get_the_ID() ) ) {
    $gatherpress_event = new \GatherPress\Core\Event( get_the_ID() );
	echo '<p class="">' . $gatherpress_event->get_display_datetime() . '</p>';
}
