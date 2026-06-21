<?php
/**
 * Venue abilities for the WordPress Abilities API.
 *
 * @package GatherPress\Core\AI
 * @since 0.34.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Utility;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Meta as Venue_Meta;
use WP_Error;

/**
 * Class Abilities_Venue.
 *
 * Handles create-venue and update-venue ability execution.
 *
 * @since 0.34.0
 */
class Abilities_Venue {

	/**
	 * Execute the create-venue ability.
	 *
	 * @since 0.34.0
	 *
	 * @param array $params Parameters including name, address, phone, website.
	 * @return array Response with created venue ID or error.
	 */
	public function execute_create_venue( array $params ): array {
		// Validate required parameters.
		if ( empty( $params['name'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Venue name is required.', 'gatherpress' ),
			);
		}

		if ( empty( $params['address'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Venue address is required.', 'gatherpress' ),
			);
		}

		/**
		 * Venue post ID or WP_Error on failure.
		 *
		 * @var int|WP_Error $venue_id
		 */
		$venue_id = wp_insert_post(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_title'   => sanitize_text_field( $params['name'] ),
				'post_content' => '<!-- wp:pattern {"slug":"gatherpress/venue-template"} /-->',
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $venue_id ) ) {
			return array(
				'success' => false,
				'message' => $venue_id->get_error_message(),
			);
		}

		$coordinates = $this->geocode_address( $params['address'] );

		$this->save_venue_editor_meta(
			$venue_id,
			array(
				'address'   => sanitize_text_field( $params['address'] ),
				'phone'     => isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '',
				'website'   => isset( $params['website'] ) ? esc_url_raw( $params['website'] ) : '',
				'latitude'  => $coordinates['latitude'],
				'longitude' => $coordinates['longitude'],
			)
		);

		return array(
			'success'  => true,
			'venue_id' => $venue_id,
			'edit_url' => get_edit_post_link( $venue_id, 'raw' ),
			'message'  => sprintf(
				/* translators: %s: venue name */
				__( 'Venue "%s" created successfully.', 'gatherpress' ),
				$params['name']
			),
		);
	}

	/**
	 * Execute the update-venue ability.
	 *
	 * @since 0.34.0
	 *
	 * @param array $params Parameters including venue_id and fields to update.
	 * @return array Response with success status or error.
	 */
	public function execute_update_venue( array $params ): array {
		if ( empty( $params['venue_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Venue ID is required.', 'gatherpress' ),
			);
		}

		$venue_id = intval( $params['venue_id'] );

		$venue = get_post( $venue_id );
		if ( ! $venue || Venue::POST_TYPE !== $venue->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Venue not found.', 'gatherpress' ),
			);
		}

		if ( isset( $params['name'] ) ) {
			wp_update_post(
				array(
					'ID'         => $venue_id,
					'post_title' => sanitize_text_field( $params['name'] ),
				)
			);
		}

		$meta_updates = array();

		if ( isset( $params['address'] ) ) {
			$meta_updates['address'] = sanitize_text_field( $params['address'] );
		}
		if ( isset( $params['phone'] ) ) {
			$meta_updates['phone'] = sanitize_text_field( $params['phone'] );
		}
		if ( isset( $params['website'] ) ) {
			$meta_updates['website'] = esc_url_raw( $params['website'] );
		}

		if ( ! empty( $meta_updates ) ) {
			$this->save_venue_editor_meta( $venue_id, $meta_updates );
		}

		if ( isset( $params['thumbnail_id'] ) ) {
			$thumbnail_id = intval( $params['thumbnail_id'] );
			$attachment   = get_post( $thumbnail_id );
			if ( $attachment && 'attachment' === $attachment->post_type && wp_attachment_is_image( $thumbnail_id ) ) {
				$thumbnail_result = set_post_thumbnail( $venue_id, $thumbnail_id );
				if ( ! $thumbnail_result ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'GatherPress AI: Failed to set thumbnail ' . $thumbnail_id . ' for venue ' . $venue_id );
				}
			}
		}

		return array(
			'success'  => true,
			'venue_id' => $venue_id,
			'edit_url' => get_edit_post_link( $venue_id, 'raw' ),
			'message'  => sprintf(
				/* translators: %s: venue name */
				__( 'Venue "%s" updated successfully.', 'gatherpress' ),
				get_the_title( $venue_id )
			),
		);
	}

	/**
	 * Save editor-writable venue meta fields.
	 *
	 * @since 0.34.0
	 *
	 * @param int                   $venue_id Venue post ID.
	 * @param array<string, string> $fields   Field values keyed by unprefixed meta suffix.
	 * @return void
	 */
	public function save_venue_editor_meta( int $venue_id, array $fields ): void {
		foreach ( Venue_Meta::EDITOR_WRITABLE_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}

			update_post_meta( $venue_id, Utility::prefix_key( $field ), $fields[ $field ] );
		}
	}

	/**
	 * Geocode an address using OpenStreetMap Nominatim API.
	 *
	 * @since 0.34.0
	 *
	 * @param string $address The address to geocode.
	 * @return array Array with 'latitude' and 'longitude' keys.
	 */
	public function geocode_address( string $address ): array {
		$encoded_address = rawurlencode( $address );
		$api_url         = "https://nominatim.openstreetmap.org/search?q={$encoded_address}&format=json&limit=1";

		$version  = defined( 'GATHERPRESS_VERSION' ) ? GATHERPRESS_VERSION : '1.0.0';
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'User-Agent' => 'GatherPress/' . $version . ' (WordPress Plugin)',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'latitude'  => '0',
				'longitude' => '0',
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! empty( $data ) && isset( $data[0]['lat'] ) && isset( $data[0]['lon'] ) ) {
			return array(
				'latitude'  => $data[0]['lat'],
				'longitude' => $data[0]['lon'],
			);
		}

		return array(
			'latitude'  => '0',
			'longitude' => '0',
		);
	}
}
