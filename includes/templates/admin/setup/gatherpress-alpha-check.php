<?php
/**
 * Admin Notice for GatherPress Alpha check.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

?>
<div class="notice notice-warning is-dismissible">
	<p>
		<?php
		echo wp_kses_post(
			__(
				'The GatherPress Alpha plugin is not installed or activated. This plugin is currently in heavy development and requires GatherPress Alpha to handle breaking changes. Please <a href="https://github.com/GatherPress/gatherpress-alpha" target="_blank">download and install GatherPress Alpha</a> to ensure compatibility and avoid issues.',
				'gatherpress'
			)
		);
		?>
	</p>
</div>
