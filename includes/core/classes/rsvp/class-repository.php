<?php
/**
 * RSVP Comment Repository.
 *
 * Handles persistence for gatherpress_rsvp comments.
 *
 * @package GatherPress\Core\Rsvp
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Provider\Base as Base_Provider;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Repository.
 *
 * Handles querying and manipulation of RSVP comments within the GatherPress plugin.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
final class Repository {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get RSVP.
	 *
	 * @param int       $post_id   The Events Post ID.
	 * @param Identity  $identity  The Identity of the RSVP response.
	 * @param Provider  $provider  The RSVP provider.
	 * @return array<int|string>
	 */
	public function get( int $post_id, Identity $identity, $provider = null ) {
		$rsvp_query = Query::get_instance();

		// Bootstrap comment query.
		$args = array(
			'post_id' => $post_id,
			'status'  => 'approve',
		);

		// Add the identity of the RSVP response.
		$args = wp_parse_args( $this->get_identity_query_args( $identity ), $args );

		// Optionally also specify the provider that issued the RSVP reponse.
		if ( $provider ) {
			$args = wp_parse_args( $this->get_provider_query_args( $provider ), $args );
		}

		$rsvp = $rsvp_query->get_rsvp( $args );

		if ( empty( $rsvp ) ) {
			return null;
		}

		return Factory
	}

	/**
	 * Persist a RSVP comment.
	 *
	 * @param Request $request             The RSVP request.
	 * @param int     $current_comment_id  The current RSVP comment ID (in case of an update).
	 */
	puiblic function save( $post_id , Attendee $attendee, $current_comment_id = 0 ) {
		$post_id = $this->event->ID;

		$args = array(
			'comment_post_ID'   => $post_id,
			'comment_author_IP' => '127.0.0.1',
			'comment_type'

			=> self::COMMENT_TYPE,
		);

		$args = Manager::filter_comment_query(  $request, $args );

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			if ( rest_is_ip_address( $remote_ip ) ) {
				$args['comment_author_IP'] = $remote_ip;
			}
		}

		if ( ! $current_comment_id ) {
			// Ensure keys that wp_filter_comment accesses without isset() are present.
			$args = array_merge(
				array(
					'comment_author'       => '',
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_author_IP'    => '127.0.0.1',
					'comment_content'      => '',
				),
				$args
			);

			// Run WordPress-native comment filters so sites can honor
			// pre_comment_user_ip, pre_comment_user_agent, etc. for privacy.
			$args       = wp_filter_comment( $args );
			$comment_id = wp_insert_comment( $args );
		} else {
			$comment_id               = $current_comment_id;
			$args['comment_ID']       = $comment_id;
			$args['comment_approved'] = 1;

			wp_update_comment( $args );
		}

		if ( empty( $comment_id ) ) {
			return null;
		}

		// If status is 'no_status', remove the record.
		if ( Status::NO_STATUS === $request->status ) {
			wp_delete_comment( $comment_id, true );

			Cache::delete( $post_id );

			return null;
		}

		wp_set_object_terms( $comment_id, $request->status->value, Status::TAXONOMY );
		wp_set_object_terms( $comment_id, $request->type, Base_Rsvp_Type::TAXONOMY );

		if ( $request->has_guests() ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_guests', $request->guests );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_guests' );
		}

		if ( $request->is_anonymous() ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', 1 );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous' );
		}

		Cache::delete( $post_id );

		return $comment_id;
	}

	/**
	 * Get query args.
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return array<array<int|string>|int|string>
	 */
	protected function get_identity_query_args( Identity $identity ) {
		$args = array();

		switch ( $identity->get_type() ) {
			case Identity_Type::EMAIL:
				$args['comment_author_email'] = $identity->value;
				break;

			case Identity_Type::URL:
				$args['comment_author_url'] = $identity->value;
				break;

			case Identity_Type::WP_USER_ID:
				$args['user_id'] = (int) $identity->value;
				break;

			default:
				$args['comment_meta']['gatherpress_rsvp_external_id'] = $identity->value;
				break;
		}

		return $args;
	}

	/**
	 * Get query args.
	 *
	 * @param Provider $provider The RSVP provider.
	 *
	 * @return array<array<int|string>|int|string>
	 */
	protected function get_provider_query_args( $provider ) {
		return array(
			'gatherpress_rsvp_provider_query' => array(
				'taxonomy' => Base_Provider::TAXONOMY,
				'terms'    => $provider->get_slug(),
				'field'    => 'slug',
			),
		);
	}

	/**
	 * Find RSVP comments by post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	public function find_by_post( int $post_id ): array {

		return get_comments(
			array(
				'post_id' => $post_id,
				'type'    => 'gatherpress_rsvp',
				'status'  => 'approve',
			)
		);
	}

	/**
	 * Find RSVP comment by identity.
	 *
	 * @param Identity $identity Identity.
	 *
	 * @return array
	 */
	public function find_by_identity( Identity $identity ): array {

		return get_comments(
			array(
				'type'       => 'gatherpress_rsvp',
				'meta_query' => array(
					array(
						'key'   => 'identity_type',
						'value' => $identity->get_type()->value,
					),
					array(
						'key'   => 'identity_value',
						'value' => (string) $identity->get_value(),
					),
				),
			)
		);
	}

	/**
	 * Apply identity to comment data array.
	 *
	 * @param Identity $identity Identity.
	 * @param array    $data     Comment data.
	 *
	 * @return array
	 */
	private function map_to_comment_data( Identity $identity, array $data ): array {
		switch ( $identity->get_type() ) {
			case Identity_Type::EMAIL:
				$data['comment_author_email'] = $identity->get_value();
				break;

			case Identity_Type::URL:
				$data['comment_author_url'] = $identity->get_value();
				break;

			case Identity_Type::WP_USER_ID:
				$data['user_id'] = (int) $identity->get_value();
				break;

			case Identity_Type::ID:
				$data['comment_meta']['external_id'] = $identity->get_value();
				break;
		}

		// Always store normalized version.
		$data['comment_meta']['gp_identity_type']  = $identity->get_type()->value;
		$data['comment_meta']['gp_identity_value'] = $identity->get_value();

		return $data;
	}
}
