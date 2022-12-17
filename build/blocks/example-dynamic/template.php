<?php
/**
 *
 */

// $an_event = new \GatherPress\Core\Event( get_the_ID() );
if ( 'gp_event' === get_post_type( get_the_ID() ) ) {
    $an_event = new \GatherPress\Core\Event( get_the_ID() );
} else {
	$an_event = '';
}
 ?>
<div <?php echo get_block_wrapper_attributes(); ?>>
    <p>
        <?php esc_html_e('Example Dynamic – hello from a dynamic block!', 'block-building-diagnostics'); ?>
    </p>
    <p>
		<?php

		printf( __( 'The post type is: %s & ', 'textdomain' ), get_post_type( get_the_ID() ) );

		echo '<pre>' . print_r( $an_event->get_display_datetime(), true ) . '</pre>';
		?>
    </p>
</div>
