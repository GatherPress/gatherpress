<?php

use GatherPress\Core\Event;
return;
$event     = new Event( get_the_ID() );
$responses = $event->rsvp->responses();
foreach ( $responses['attending']['responses'] as $response ) {
	$comment_id           = $response['commentId'];
	$filter_block_context = static function ( $context ) use ( $comment_id ) {
		$context['commentId'] = $comment_id;
		return $context;
	};
	?>
	<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
		<?php echo $comment_id; ?>
	</div>
	<?php
}
