<?php

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Layout {

	use Singleton;

	/**
	 * Layout constructor.
	 */
	protected function __construct() {

		$this->_setup_hooks();

	}

	protected function _setup_hooks() {

		add_action( 'gatherpress_before_content', [ $this, 'upcoming_events' ] );

	}

	public function upcoming_events() {

		if ( is_home() ) {
			echo Helper::render_template(
				GATHERPRESS_CORE_PATH . '/template-parts/upcoming-events.php',
				[]
			);
			?>
			<h2 class="text-3xl mb-4">
				<?php echo esc_html_e( 'Past Events' ); ?>
			</h2>
			<?php
		}
	}

}

// EOF
