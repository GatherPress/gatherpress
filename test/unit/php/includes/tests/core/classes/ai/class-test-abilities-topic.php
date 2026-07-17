<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Abilities_Topic.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Abilities_Topic;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities_Topic.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Abilities_Topic
 */
class Test_Abilities_Topic extends Base {
	/**
	 * Returns a topic abilities handler instance.
	 *
	 * @return Abilities_Topic
	 */
	private function get_topic_instance(): Abilities_Topic {
		return new Abilities_Topic();
	}

	/**
	 * Coverage for execute_list_topics method with no topics.
	 *
	 * @covers ::execute_list_topics
	 *
	 * @return void
	 */
	public function test_execute_list_topics_with_no_topics(): void {
		$handler = $this->get_topic_instance();
		$result  = $handler->execute_list_topics();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertEmpty( $result['data'], 'Failed to assert data is empty.' );
	}

	/**
	 * Coverage for execute_list_topics method with topics.
	 *
	 * @covers ::execute_list_topics
	 *
	 * @return void
	 */
	public function test_execute_list_topics_with_topics(): void {
		wp_insert_term( 'Workshop', 'gatherpress_topic' );
		wp_insert_term( 'Social', 'gatherpress_topic' );

		$handler = $this->get_topic_instance();
		$result  = $handler->execute_list_topics();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertCount( 2, $result['data'], 'Failed to assert data has 2 topics.' );

		$workshop = null;
		foreach ( $result['data'] as $topic ) {
			if ( 'Workshop' === $topic['name'] ) {
				$workshop = $topic;
				break;
			}
		}

		$this->assertNotNull( $workshop, 'Failed to find Workshop topic.' );
		$this->assertArrayHasKey( 'id', $workshop, 'Failed to assert topic has id.' );
		$this->assertSame( 'Workshop', $workshop['name'], 'Failed to assert topic name.' );
	}

	/**
	 * Coverage for execute_create_topic method with valid parameters.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_valid_params(): void {
		$handler = $this->get_topic_instance();
		$params  = array(
			'name'        => 'Book Club',
			'description' => 'Events for book club meetings',
		);
		$result  = $handler->execute_create_topic( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'topic_id', $result, 'Failed to assert topic_id exists.' );
		$this->assertSame( 'Book Club', $result['name'], 'Failed to assert topic name.' );

		$topic = get_term( $result['topic_id'], 'gatherpress_topic' );
		$this->assertNotNull( $topic, 'Failed to assert topic exists.' );
		$this->assertSame( 'Book Club', $topic->name, 'Failed to assert topic name matches.' );
	}

	/**
	 * Coverage for execute_create_topic method without name.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_without_name(): void {
		$handler = $this->get_topic_instance();
		$result  = $handler->execute_create_topic( array() );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'required', $result['message'], 'Failed to assert error message.' );
	}

	/**
	 * Coverage for execute_create_topic with parent_id.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_parent_id(): void {
		$parent_result = wp_insert_term( 'Parent Topic', 'gatherpress_topic' );
		$parent_id     = $parent_result['term_id'];

		$handler = $this->get_topic_instance();
		$params  = array(
			'name'      => 'Child Topic',
			'parent_id' => $parent_id,
		);
		$result  = $handler->execute_create_topic( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'topic_id', $result );

		$topic = get_term( $result['topic_id'], 'gatherpress_topic' );
		$this->assertSame( $parent_id, $topic->parent, 'Failed to assert parent topic was set.' );
	}

	/**
	 * Coverage for execute_create_topic with WP_Error from wp_insert_term.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_wp_error(): void {
		wp_insert_term( 'Duplicate Topic', 'gatherpress_topic' );

		$handler = $this->get_topic_instance();
		$params  = array(
			'name' => 'Duplicate Topic',
		);
		$result  = $handler->execute_create_topic( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'already exists', $result['message'] );
	}

	/**
	 * Coverage for execute_create_topic with description.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_description(): void {
		$handler = $this->get_topic_instance();
		$params  = array(
			'name'        => 'Topic with Description',
			'description' => 'This is a test topic description',
		);
		$result  = $handler->execute_create_topic( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		$topic = get_term( $result['topic_id'], 'gatherpress_topic' );
		$this->assertSame( 'This is a test topic description', $topic->description );
	}

	/**
	 * Coverage for execute_list_topics with WP_Error from get_terms.
	 *
	 * @covers ::execute_list_topics
	 *
	 * @return void
	 */
	public function test_execute_list_topics_with_wp_error(): void {
		add_filter(
			'get_terms',
			function ( $terms, $taxonomies, $args ) {
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				$args = $args;
				if ( in_array( 'gatherpress_topic', (array) $taxonomies, true ) ) {
					return new \WP_Error( 'term_error', 'Failed to get terms' );
				}
				return $terms;
			},
			10,
			3
		);

		$handler = $this->get_topic_instance();
		$result  = $handler->execute_list_topics();

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Failed to get terms', $result['message'] );
	}
}
