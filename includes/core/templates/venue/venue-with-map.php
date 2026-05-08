<?php
/**
 * "Venue with Map" starter pattern.
 *
 * Seeds a single `gatherpress/venue` block (address + phone + website +
 * embedded map) when chosen from the block editor's starter pattern
 * modal. `patternPicked: true` skips the venue block's in-block pattern
 * picker so the canonical layout renders directly; authors can still
 * swap layouts via the block toolbar's "Choose pattern" action.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

return array(
	'name'        => 'gatherpress/venue-with-map',
	'title'       => __( 'Venue with Map', 'gatherpress' ),
	'description' => __(
		'Address, contact details, and an embedded map.',
		'gatherpress'
	),
	'content'     => '<!-- wp:gatherpress/venue {"patternPicked":true} /-->',
);
