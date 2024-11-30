<?php
//use GatherPress\Core\Event;
//
//$event     = new Event( get_the_ID() );
//$responses = $event->rsvp->responses();
//
//foreach ( $responses['attending']['responses'] as $response ) {
//	?>
<!--	<div --><?php //echo wp_kses_data( get_block_wrapper_attributes() ); ?><!---->
<!--		--><?php //echo $response['commentId']; ?>
<!--	</div>-->
<!--	--><?php
//}
?>

<?php
use GatherPress\Core\Event;

/**
 * Render callback for the block.
 *
 * @param array  $attributes Block attributes passed from the editor.
 * @param string $content    Block content passed from the editor.
 * @param array  $block      The full block instance, including context.
 *
 * @return string Rendered block HTML.
 */
function render_gatherpress_block( $attributes, $content, $block ) {
    // Get the current post ID from the block context.
    $post_id = $block->context['postId'] ?? get_the_ID();

    // Create an Event instance.
    $event = new Event( $post_id );
    $responses = $event->rsvp->responses();

    // Start capturing the output.
    ob_start();

    // Loop through responses and output the block's HTML.
    foreach ( $responses['attending']['responses'] as $response ) {
        ?>
        <div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
            <strong>Comment ID:</strong> <?php echo esc_html( $response['commentId'] ); ?>
        </div>
        <?php
    }

    // Return the captured output.
    return ob_get_clean();
}

// Register the block with the render callback.
register_block_type(
    'gatherpress/rsvp-template',
    array(
        'render_callback' => 'render_gatherpress_block',
    )
);
?>
