<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\External\Post_Type_Feed.
 *
 * @package GatherPress\Core\Calendar\External
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Calendar\External;

use GatherPress\Core\Calendar\External\Post_Type_Feed;
use GatherPress\Core\Calendar\External\Setup;
use GatherPress\Core\Calendar\External\Template;
use GatherPress\Core\Event\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Post_Type_Feed.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\External\Post_Type_Feed
 * @group              endpoints
 */
class Test_Post_Type_Feed extends Base {

	/**
	 * Permalink structure before the test.
	 *
	 * @var string
	 */
	private string $permalink_structure = '';

	/**
	 * Saves the permalink structure.
	 *
	 * @return void
	 */
	public function set_up(): void {
		global $wp_rewrite;

		parent::set_up();

		$this->permalink_structure = (string) $wp_rewrite->permalink_structure;
	}

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$query_var = 'gatherpress_ext_calendar';
		$post_type = 'gatherpress_event';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Feed( $types, $query_var, $post_type );

		$this->assertSame(
			$query_var,
			$instance->query_var,
			'Failed to assert that query_var is persisted.'
		);
		$this->assertSame(
			get_post_type_object( $post_type ),
			$instance->type_object,
			'Failed to assert that type_object is persisted.'
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
			'%s/feed/(%s)/?$',
			$instance->reg_ex,
			'Failed to assert that reg_ex matches the post-type feed pattern.'
		);
		$this->assertSame(
			'post_type',
			$instance->object_type,
			'Failed to assert that object_type defaults to post_type.'
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
		$post_type = 'gatherpress_event';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Feed( $types, $query_var, $post_type );

		// Positive branch: post type archive in feed mode.
		$this->go_to( get_post_type_archive_link( $post_type ) );
		$GLOBALS['wp_query']->is_feed = true;

		$this->assertTrue(
			$instance->is_valid(),
			'Failed to assert that is_valid returns true for a post-type archive feed.'
		);

		// Negative branch: home page (neither archive nor feed).
		$this->go_to( home_url( '/' ) );

		$this->assertFalse(
			$instance->is_valid(),
			'Failed to assert that is_valid returns false outside an archive feed.'
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
		$post_type = 'gatherpress_event';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Feed( $types, $query_var, $post_type );

		$this->assertSame(
			array(
				'post_type' => 'gatherpress_event',
				'feed'      => '$matches[1]',
				$query_var  => '$matches[1]',
			),
			$instance->get_rewrite_atts(),
			'Failed to assert that rewrite attributes match the post-type feed shape.'
		);
	}

	/**
	 * Coverage for get_regex_pattern with the event post type's defaults.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_get_regex_pattern_uses_rewrite_slug(): void {
		$instance = $this->get_ical_feed( 'gatherpress_event' );

		$this->assertSame(
			'event/feed/(ical)/?$',
			Utility::invoke_hidden_method( $instance, 'get_regex_pattern' ),
			'Failed to assert that the pattern uses the rewrite slug when has_archive is true.'
		);
	}

	/**
	 * Coverage for get_regex_pattern when has_archive is a string.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_get_regex_pattern_uses_archive_slug(): void {
		$this->set_permalinks( '/%postname%/' );
		$this->register_feed_post_type( 'things', false );

		$instance = $this->get_ical_feed( 'gatherpress_feed_pt' );
		$pattern  = Utility::invoke_hidden_method( $instance, 'get_regex_pattern' );

		$this->assertSame(
			'things/feed/(ical)/?$',
			$pattern,
			'Failed to assert that a string has_archive replaces the rewrite slug.'
		);
		$this->assert_pattern_matches_feed_link( $pattern );
	}

	/**
	 * Coverage for get_regex_pattern when has_archive is an empty string.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_get_regex_pattern_ignores_empty_archive_slug(): void {
		$this->register_feed_post_type( '', false );

		$instance = $this->get_ical_feed( 'gatherpress_feed_pt' );

		$this->assertSame(
			'thing/feed/(ical)/?$',
			Utility::invoke_hidden_method( $instance, 'get_regex_pattern' ),
			'Failed to assert that an empty has_archive falls back to the rewrite slug.'
		);
	}

	/**
	 * Coverage for get_regex_pattern when the post type uses the permalink front.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_get_regex_pattern_adds_permalink_front(): void {
		$this->set_permalinks( '/blog/%postname%/' );
		$this->register_feed_post_type( true, true );

		$instance = $this->get_ical_feed( 'gatherpress_feed_pt' );
		$pattern  = Utility::invoke_hidden_method( $instance, 'get_regex_pattern' );

		$this->assertSame(
			'blog/thing/feed/(ical)/?$',
			$pattern,
			'Failed to assert that with_front adds the permalink front.'
		);
		$this->assert_pattern_matches_feed_link( $pattern );
	}

	/**
	 * Coverage for the archive feed rule of a custom event post type.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_custom_event_post_type_feed_rule_uses_archive_slug(): void {
		global $wp_rewrite;

		$this->set_permalinks( '/%postname%/' );
		$this->register_feed_post_type( 'things', false, array( 'title', Event::SUPPORT ) );

		Setup::get_instance()->init_events( 'gatherpress_feed_pt' );

		$this->assertArrayHasKey(
			'things/feed/(ical)/?$',
			$wp_rewrite->extra_rules_top,
			'Failed to assert that a custom event post type gets its archive feed rule under the archive slug.'
		);
		$this->assert_pattern_matches_feed_link( 'things/feed/(ical)/?$' );
	}

	/**
	 * Builds an iCal feed endpoint for a post type.
	 *
	 * @param string $post_type The post type name.
	 *
	 * @return Post_Type_Feed The feed endpoint.
	 */
	private function get_ical_feed( string $post_type ): Post_Type_Feed {
		return new Post_Type_Feed(
			array(
				new Template(
					'ical',
					static function () {
						return array( 'file_name' => 'foo.php' );
					}
				),
			),
			'gatherpress_ext_calendar',
			$post_type
		);
	}

	/**
	 * Registers a post type with the given archive and front settings.
	 *
	 * @param bool|string $has_archive The has_archive argument.
	 * @param bool        $with_front  The rewrite with_front argument.
	 * @param string[]    $supports    The post type supports.
	 *
	 * @return void
	 */
	private function register_feed_post_type(
		$has_archive,
		bool $with_front,
		array $supports = array( 'title' )
	): void {
		register_post_type(
			'gatherpress_feed_pt',
			array(
				'public'      => true,
				'supports'    => $supports,
				'has_archive' => $has_archive,
				'rewrite'     => array(
					'slug'       => 'thing',
					'with_front' => $with_front,
				),
			)
		);
	}

	/**
	 * Sets the permalink structure.
	 *
	 * @param string $structure The permalink structure.
	 *
	 * @return void
	 */
	private function set_permalinks( string $structure ): void {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( $structure );
	}

	/**
	 * Asserts the pattern matches the path of the advertised archive feed link.
	 *
	 * @param string $pattern The rewrite rule pattern.
	 *
	 * @return void
	 */
	private function assert_pattern_matches_feed_link( string $pattern ): void {
		$link = (string) get_post_type_archive_feed_link( 'gatherpress_feed_pt', 'ical' );
		$path = ltrim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' );

		$this->assertMatchesRegularExpression(
			'#^' . $pattern . '#',
			$path,
			'Failed to assert that the rule matches the advertised feed link.'
		);
	}

	/**
	 * Unregisters the test post type and restores the permalink structure.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rewrite;

		unregister_post_type( 'gatherpress_feed_pt' );

		if ( $wp_rewrite->permalink_structure !== $this->permalink_structure ) {
			$this->set_permalinks( $this->permalink_structure );
		}

		parent::tear_down();
	}
}
