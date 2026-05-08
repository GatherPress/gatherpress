<?php
/**
 * Test fixture — array without a `name` key. The loader skips entries
 * like this so callers never try to register a nameless pattern.
 *
 * @package GatherPress\Tests
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

return array(
	'content' => '<p>nope</p>',
);
