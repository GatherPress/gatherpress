<?php
/**
 * Unit tests for the venue-map dimension helpers and render sizing.
 *
 * Covers the static attribute readers on GatherPress\Core\Venue\Map\Dimensions
 * (`get_dimension_value`, `parse_px_dimension`, `to_css_dimension`) and
 * the wrapper sizing rules the block's render.php emits from them. The
 * render tests use venues without coordinates so the template takes the
 * placeholder path — no tile fetches or image compositing — while still
 * exercising every sizing branch on the wrapper.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Dimensions;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Map_Dimensions.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Dimensions
 */
class Test_Map_Dimensions extends Base {

	/**
	 * Resets the site-wide default height between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Settings::get_instance()->set( 'venue_map_default_height', '' );

		parent::tearDown();
	}

	/**
	 * Create a venue post with an address but no coordinates.
	 *
	 * The missing coordinates force render.php down the placeholder path,
	 * which skips descriptor generation (no HTTP, no GD) while still
	 * emitting the sized wrapper the sizing tests assert against.
	 *
	 * @since 0.35.0
	 *
	 * @return int The venue post ID.
	 */
	protected function create_venue_without_coordinates(): int {
		$venue_post = $this->mock->post(
			array(
				'post_title' => 'Dimension Test Venue',
				'post_type'  => Venue::POST_TYPE,
			)
		)->get();

		add_post_meta( $venue_post->ID, 'gatherpress_address', '1 Infinite Loop, Cupertino, CA' );

		return $venue_post->ID;
	}

	/**
	 * Render the venue-map block in the context of the given venue.
	 *
	 * @since 0.35.0
	 *
	 * @param int    $venue_id        The venue post ID to render against.
	 * @param string $attributes_json JSON attribute payload for the block comment.
	 *
	 * @return string The rendered block markup.
	 */
	protected function render_block( int $venue_id, string $attributes_json = '' ): string {
		$this->go_to( get_permalink( $venue_id ) );

		$block_comment = '' !== $attributes_json
			? sprintf( '<!-- wp:gatherpress/venue-map %s /-->', $attributes_json )
			: '<!-- wp:gatherpress/venue-map /-->';

		return do_blocks( $block_comment );
	}

	/**
	 * Returns the style.dimensions value when present.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_reads_style_dimensions(): void {
		$attributes = array(
			'style' => array(
				'dimensions' => array(
					'height' => '250px',
				),
			),
		);

		$this->assertSame(
			'250px',
			Dimensions::get_dimension_value( $attributes, 'height' ),
			'The style.dimensions value should be returned.'
		);
	}

	/**
	 * Treats an empty style value as unset.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_treats_empty_style_value_as_unset(): void {
		$attributes = array(
			'style' => array(
				'dimensions' => array(
					'height' => '',
				),
			),
		);

		$this->assertNull(
			Dimensions::get_dimension_value( $attributes, 'height' ),
			'An empty style value should read as unset.'
		);
	}

	/**
	 * Ignores pre-0.35 numeric attributes and non-string style values.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_ignores_legacy_and_non_string_values(): void {
		$this->assertNull(
			Dimensions::get_dimension_value( array( 'height' => 300 ), 'height' ),
			'Pre-0.35 numeric attributes should read as unset — the Alpha migration rewrites them.'
		);
		$this->assertNull(
			Dimensions::get_dimension_value(
				array( 'style' => array( 'dimensions' => array( 'height' => 300 ) ) ),
				'height'
			),
			'A non-string style value should read as unset.'
		);
		$this->assertNull(
			Dimensions::get_dimension_value( array(), 'height' ),
			'A dimension carried by neither shape should read as unset.'
		);
	}

	/**
	 * Numbers pass through parse_px_dimension with rounding and clamping.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::parse_px_dimension
	 *
	 * @return void
	 */
	public function test_parse_px_dimension_handles_numbers(): void {
		$this->assertSame( 512, Dimensions::parse_px_dimension( 512 ), 'A whole number should pass through.' );
		$this->assertSame( 513, Dimensions::parse_px_dimension( 512.6 ), 'A fractional number should round.' );
		$this->assertSame( 0, Dimensions::parse_px_dimension( -20 ), 'A negative number should clamp to 0.' );
	}

	/**
	 * Px and unitless strings parse to whole pixels.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::parse_px_dimension
	 *
	 * @return void
	 */
	public function test_parse_px_dimension_parses_px_strings(): void {
		$this->assertSame( 512, Dimensions::parse_px_dimension( '512px' ), 'A px-suffixed string should parse.' );
		$this->assertSame( 512, Dimensions::parse_px_dimension( '512' ), 'A unitless numeric string should parse.' );
		$this->assertSame(
			512,
			Dimensions::parse_px_dimension( '  512.4 px ' ),
			'Whitespace should be tolerated and fractional strings rounded.'
		);
	}

	/**
	 * Non-px units, keywords, and unset values resolve to 0 (auto).
	 *
	 * @since 0.35.0
	 *
	 * @covers ::parse_px_dimension
	 *
	 * @return void
	 */
	public function test_parse_px_dimension_resolves_non_px_values_to_auto(): void {
		$this->assertSame( 0, Dimensions::parse_px_dimension( '50%' ), 'Percent values are not px-expressible.' );
		$this->assertSame( 0, Dimensions::parse_px_dimension( '20rem' ), 'Rem values are not px-expressible.' );
		$this->assertSame( 0, Dimensions::parse_px_dimension( 'fit-content' ), 'CSS keywords are not px-expressible.' );
		$this->assertSame( 0, Dimensions::parse_px_dimension( '' ), 'An empty string should resolve to auto.' );
		$this->assertSame( 0, Dimensions::parse_px_dimension( null ), 'Null should resolve to auto.' );
		$this->assertSame(
			0,
			Dimensions::parse_px_dimension( true ),
			'A non-scalar-dimension type should resolve to auto.'
		);
	}

	/**
	 * Numbers gain a px suffix; strings pass through trimmed.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::to_css_dimension
	 *
	 * @return void
	 */
	public function test_to_css_dimension_normalizes_values(): void {
		$this->assertSame( '300px', Dimensions::to_css_dimension( 300 ), 'An int should gain a px suffix.' );
		$this->assertSame(
			'301px',
			Dimensions::to_css_dimension( 300.6 ),
			'A float should round then gain px.'
		);
		$this->assertSame( '50%', Dimensions::to_css_dimension( '50%' ), 'A style string should pass through.' );
		$this->assertSame( '640px', Dimensions::to_css_dimension( ' 640px ' ), 'A style string should be trimmed.' );
	}

	/**
	 * An explicit style height stamps inline and suppresses the ratio.
	 *
	 * Width is never stamped — the wrapper always spans its container.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_stamps_style_height_and_no_width(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"250px"}}}'
		);

		$this->assertStringContainsString( 'height:250px', $output, 'The style height should stamp inline.' );
		$this->assertStringNotContainsString(
			'aspect-ratio',
			$output,
			'An explicit height should suppress the ratio.'
		);
		$this->assertStringNotContainsString( 'width:', $output, 'Width is never stamped.' );
	}

	/**
	 * The site-wide default height applies when the block has none.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_falls_back_to_site_default_height(): void {
		Settings::get_instance()->set( 'venue_map_default_height', 400 );

		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block( $venue_id, '{"renderMode":"static"}' );

		$this->assertStringContainsString(
			'height:400px',
			$output,
			'The Settings default height should stamp when the block has none.'
		);
		$this->assertStringNotContainsString(
			'aspect-ratio',
			$output,
			'A resolved height should suppress the ratio.'
		);
	}

	/**
	 * A block height wins over the site-wide default.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_block_height_beats_site_default(): void {
		Settings::get_instance()->set( 'venue_map_default_height', 400 );

		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"250px"}}}'
		);

		$this->assertStringContainsString( 'height:250px', $output, 'The block height should win.' );
		$this->assertStringNotContainsString( 'height:400px', $output, 'The Settings default should be ignored.' );
	}

	/**
	 * Non-px units apply as CSS.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_passes_non_px_units_through(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"50%"}}}'
		);

		$this->assertStringContainsString( 'height:50%', $output, 'A percent height should stamp as authored.' );
	}

	/**
	 * A height value safecss rejects degrades to the ratio.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_drops_unsafe_height_values(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"expression(alert(1))"}}}'
		);

		$this->assertStringNotContainsString( 'expression', $output, 'Unsafe CSS should never reach the wrapper.' );
		$this->assertStringContainsString(
			'aspect-ratio:2/1',
			$output,
			'The rejected height should degrade to the ratio.'
		);
	}

	/**
	 * With no height at all, the ratio shapes the wrapper.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_uses_ratio_when_no_height_set(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block( $venue_id, '{"renderMode":"static","aspectRatio":"16/9"}' );

		$this->assertStringNotContainsString( 'height:', $output, 'No height should stamp when unset.' );
		$this->assertStringContainsString(
			'aspect-ratio:16/9',
			$output,
			'The ratio should shape the wrapper.'
		);
	}

	/**
	 * The interactive payload carries a pixel height for embeds that
	 * size themselves.
	 *
	 * No descriptor exists for a coordinate-less venue, so the payload
	 * falls back to the parsed block height (or the default when the
	 * block has no px-expressible height).
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_interactive_payload_height_fallbacks(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"interactive","style":{"dimensions":{"height":"250px"}}}'
		);

		$this->assertStringContainsString(
			'&quot;mapHeight&quot;:250',
			$output,
			'The payload should carry the parsed block height.'
		);

		$output = $this->render_block( $venue_id, '{"renderMode":"interactive"}' );

		$this->assertStringContainsString(
			sprintf( '&quot;mapHeight&quot;:%d', Map::DEFAULT_HEIGHT ),
			$output,
			'Without a px-expressible height the payload should carry the default.'
		);
	}
}
