<?php
/**
 * Template for handling before event content.
 *
 * @package gp_template
 */

?>

<div class="flex items-center pt-4 pb-4">
	<div class="w-9/12">
		<span class="m-0 p-0 font-bold text-2xl">
			<?php echo esc_html( $event->get_datetime_start( get_the_ID(), 'l, F j, Y' ) ); ?>
		</span>
	</div>
	<div class="w-3/12">
		<div id="attendance_button_container"></div>
	</div>
</div>
