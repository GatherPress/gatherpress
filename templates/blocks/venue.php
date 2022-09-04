<?php
/**
 * Template for Venue block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $gatherpress_block_attrs ) || ! is_array( $gatherpress_block_attrs ) ) {
	return;
}

$venue = get_post( intval( $gatherpress_block_attrs['venueId'] ) );

if ( Venue::POST_TYPE !== get_post_type( $venue ) ) {
	return;
}

$venue_information = json_decode( get_post_meta( $venue->ID, '_venue_information', true ) );
?>
<div class="gp-venue">
<!--	<div class="has-medium-font-size">-->
<!--		<strong>--><?php //echo esc_html( $venue->post_title ); ?><!--</strong>-->
<!--	</div>-->
	<?php
	Utility::render_template(
		sprintf( '%s/templates/blocks/venue-information.php', GATHERPRESS_CORE_PATH ),
		array(
			'gatherpress_block_attrs' => array(
				'name'        => $venue->post_title,
				'fullAddress' => $venue_information->fullAddress ?? '',
				'phoneNumber' => $venue_information->phoneNumber ?? '',
				'website'     => $venue_information->website ?? '',
			),
		),
		true
	);
	?>
</div>
