<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Taxonomy_Feed.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Taxonomy_Feed;
use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;

/**
 * Class Test_Taxonomy_Feed.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Taxonomy_Feed
 * @group              endpoints
 */
class Test_Taxonomy_Feed extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$query_var = 'gatherpress_ext_calendar';
		$taxonomy  = 'gatherpress_topic';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		// Default taxonomy defaults to gatherpress_topic when not supplied.
		$instance = new Taxonomy_Feed( $types, $query_var );

		$this->assertSame(
			$query_var,
			$instance->query_var,
			'Failed to assert that query_var is persisted.'
		);
		$this->assertSame(
			get_taxonomy( $taxonomy ),
			$instance->type_object,
			'Failed to assert that type_object defaults to gatherpress_topic.'
		);
		$this->assertSame(
			array( $instance, 'is_valid' ),
			$instance->validation_callback,
			'Failed to assert that validation_callback points to is_valid.'
		);
		$this->assertSame(
			$types,
			$instance->types,
			'Failed to assert that endpoint types are persisted.'
		);
		$this->assertSame(
			'%s/([^/]+)/feed/(%s)/?$',
			$instance->reg_ex,
			'Failed to assert that reg_ex matches the taxonomy feed pattern.'
		);
		$this->assertSame(
			'taxonomy',
			$instance->object_type,
			'Failed to assert that object_type is set to taxonomy.'
		);
	}

	/**
	 * Coverage for is_valid method.
	 *
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_is_valid(): void {
		$query_var = 'gatherpress_ext_calendar';
		$taxonomy  = 'gatherpress_topic';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Taxonomy_Feed( $types, $query_var, $taxonomy );

		// Positive branch: taxonomy term archive in feed mode. `is_tax()`
		// inspects the queried object, so we have to materialize a real term
		// and set the matching query vars + flags rather than just flipping
		// `is_tax = true` (which doesn't carry the taxonomy name).
		$term_id  = $this->factory->term->create( array( 'taxonomy' => $taxonomy ) );
		$term     = get_term( $term_id, $taxonomy );
		$wp_query = $GLOBALS['wp_query'];
		$wp_query->init();
		$wp_query->is_tax            = true;
		$wp_query->is_feed           = true;
		$wp_query->queried_object    = $term;
		$wp_query->queried_object_id = $term->term_id;
		$wp_query->set( 'taxonomy', $taxonomy );
		$wp_query->set( $taxonomy, $term->slug );

		$this->assertTrue(
			$instance->is_valid(),
			'Failed to assert that is_valid returns true for a taxonomy feed request.'
		);

		// Negative branch: home page.
		$this->go_to( home_url( '/' ) );

		$this->assertFalse(
			$instance->is_valid(),
			'Failed to assert that is_valid returns false outside a taxonomy feed request.'
		);
	}

	/**
	 * Coverage for get_rewrite_atts method.
	 *
	 * @covers ::get_rewrite_atts
	 *
	 * @return void
	 */
	public function test_get_rewrite_atts(): void {
		$query_var = 'gatherpress_ext_calendar';
		$taxonomy  = 'gatherpress_topic';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Taxonomy_Feed( $types, $query_var, $taxonomy );

		$this->assertSame(
			array(
				'taxonomy'          => 'gatherpress_topic',
				'gatherpress_topic' => '$matches[1]',
				'feed'              => '$matches[2]',
				$query_var          => '$matches[2]',
			),
			$instance->get_rewrite_atts(),
			'Failed to assert that rewrite attributes match the taxonomy feed shape.'
		);
	}
}
