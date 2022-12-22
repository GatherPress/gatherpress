<?php

use GatherPress\Core\Event;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

$gatherpress_event = new Event( get_the_ID() );

$gatherpress_venue = get_post( intval( $gatherpress_block_attrs['venueId'] ) );

$gatherpress_venue_information = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );
// echo '<h4>PHP callback for <span  style="color:maroon;">' . __DIR__ . '</span></h4>';
// echo '<pre>$gatherpress_event ' . get_the_ID() . print_r( $gatherpress_event, true ) . '</pre>';