<div class="flex">
	<div class="w-9/12">
		<h3 class="text-2xl">
			<?php echo esc_html( $event->get_datetime_start( get_the_ID(), 'l, F j, Y' ) ); ?>
		</h3>
	</div>
	<div class="w-3/12">
		<div id="attendance_button_container"></div>
	</div>
</div>

