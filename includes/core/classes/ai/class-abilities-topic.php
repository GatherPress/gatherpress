<?php
/**
 * Topic abilities for the WordPress Abilities API.
 *
 * @package GatherPress\Core\AI
 * @since 0.34.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Topic;

/**
 * Class Abilities_Topic.
 *
 * Handles list-topics and create-topic ability execution.
 *
 * @since 0.34.0
 */
class Abilities_Topic {

	/**
	 * Execute the list-topics ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $_params Optional parameters (currently unused).
	 * @return array List of topics with their IDs and names.
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,Squiz.Commenting.FunctionComment.Missing
	public function execute_list_topics( array $_params = array() ): array {
		$topics = get_terms(
			array(
				'taxonomy'   => Topic::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $topics ) ) {
			return array(
				'success' => false,
				'message' => $topics->get_error_message(),
			);
		}

		$topic_list = array();
		foreach ( $topics as $topic ) {
			$topic_list[] = array(
				'id'          => $topic->term_id,
				'name'        => $topic->name,
				'slug'        => $topic->slug,
				'description' => $topic->description,
				'parent'      => $topic->parent,
			);
		}

		return array(
			'success' => true,
			'data'    => $topic_list,
			'message' => sprintf(
				/* translators: %d: number of topics */
				_n(
					'Found %d topic',
					'Found %d topics',
					count( $topic_list ),
					'gatherpress'
				),
				count( $topic_list )
			),
		);
	}

	/**
	 * Execute the create-topic ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including name, description, and parent_id.
	 * @return array Result with topic ID or error.
	 */
	public function execute_create_topic( array $params = array() ): array {
		// Validate required parameters.
		if ( empty( $params['name'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Topic name is required.', 'gatherpress' ),
			);
		}

		$args = array(
			'description' => ! empty( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
		);

		if ( ! empty( $params['parent_id'] ) ) {
			$args['parent'] = intval( $params['parent_id'] );
		}

		$result = wp_insert_term(
			sanitize_text_field( $params['name'] ),
			Topic::TAXONOMY,
			$args
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$topic    = get_term( $result['term_id'], Topic::TAXONOMY );
		$edit_url = get_edit_term_link( $result['term_id'], Topic::TAXONOMY );

		return array(
			'success'  => true,
			'topic_id' => $result['term_id'],
			'name'     => $topic->name,
			'edit_url' => $edit_url,
			'message'  => sprintf(
				/* translators: %s: topic name */
				__( 'Topic "%s" created successfully.', 'gatherpress' ),
				$topic->name
			),
		);
	}
}
