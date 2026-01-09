<?php
/**
 * Test-only: Enable REST API for GatherPress events.
 */

add_action( 'init', function () {
    if ( ! post_type_exists( 'gatherpress_event' ) ) {
        return;
    }

    register_post_type(
        'gatherpress_event',
        [
            'label'        => 'Events',
            'public'       => true,
            'show_in_rest' => true,
            'rest_base'    => 'gatherpress-events',
            'supports'     => [ 'title', 'editor' ],
        ]
    );
}, 20 );
