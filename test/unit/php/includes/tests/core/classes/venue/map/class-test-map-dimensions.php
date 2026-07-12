<?php
/**
 * Unit tests for the venue-map dimension helpers and render sizing.
 *
 * Covers the static attribute readers on GatherPress\Core\Venue\Map\Map
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

use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Map_Dimensions.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Map
 */
class Test_Map_Dimensions extends Base {

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
	 * Style.dimensions value wins over the legacy attribute.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_prefers_style_dimensions(): void {
		$attributes = array(
			'width' => 640,
			'style' => array(
				'dimensions' => array(
					'width' => '512px',
				),
			),
		);

		$this->assertSame(
			'512px',
			Map::get_dimension_value( $attributes, 'width' ),
			'The style.dimensions value should win over the legacy attribute.'
		);
	}

	/**
	 * An empty style value falls through to the legacy attribute.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_skips_empty_style_value(): void {
		$attributes = array(
			'height' => 300,
			'style'  => array(
				'dimensions' => array(
					'height' => '',
				),
			),
		);

		$this->assertSame(
			300,
			Map::get_dimension_value( $attributes, 'height' ),
			'An empty style value should fall through to the legacy attribute.'
		);
	}

	/**
	 * Positive legacy numbers (int and float) are returned as-is.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_falls_back_to_positive_legacy(): void {
		$this->assertSame(
			640,
			Map::get_dimension_value( array( 'width' => 640 ), 'width' ),
			'A positive legacy int should be returned.'
		);
		$this->assertSame(
			320.5,
			Map::get_dimension_value( array( 'height' => 320.5 ), 'height' ),
			'A positive legacy float should be returned.'
		);
	}

	/**
	 * Zero, negative, and non-numeric legacy values read as unset.
	 *
	 * @since 0.35.0
	 *
	 * @covers ::get_dimension_value
	 *
	 * @return void
	 */
	public function test_get_dimension_value_treats_non_positive_legacy_as_unset(): void {
		$this->assertNull(
			Map::get_dimension_value( array( 'width' => 0 ), 'width' ),
			'Legacy 0 means "auto" and should read as unset.'
		);
		$this->assertNull(
			Map::get_dimension_value( array( 'height' => -5 ), 'height' ),
			'A negative legacy value should read as unset.'
		);
		$this->assertNull(
			Map::get_dimension_value( array( 'width' => '640' ), 'width' ),
			'A numeric string in the legacy slot should read as unset (the attribute is typed number).'
		);
		$this->assertNull(
			Map::get_dimension_value( array(), 'height' ),
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
		$this->assertSame( 512, Map::parse_px_dimension( 512 ), 'A whole number should pass through.' );
		$this->assertSame( 513, Map::parse_px_dimension( 512.6 ), 'A fractional number should round.' );
		$this->assertSame( 0, Map::parse_px_dimension( -20 ), 'A negative number should clamp to 0.' );
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
		$this->assertSame( 512, Map::parse_px_dimension( '512px' ), 'A px-suffixed string should parse.' );
		$this->assertSame( 512, Map::parse_px_dimension( '512' ), 'A unitless numeric string should parse.' );
		$this->assertSame(
			512,
			Map::parse_px_dimension( '  512.4 px ' ),
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
		$this->assertSame( 0, Map::parse_px_dimension( '50%' ), 'Percent values are not px-expressible.' );
		$this->assertSame( 0, Map::parse_px_dimension( '20rem' ), 'Rem values are not px-expressible.' );
		$this->assertSame( 0, Map::parse_px_dimension( 'fit-content' ), 'CSS keywords are not px-expressible.' );
		$this->assertSame( 0, Map::parse_px_dimension( '' ), 'An empty string should resolve to auto.' );
		$this->assertSame( 0, Map::parse_px_dimension( null ), 'Null should resolve to auto.' );
		$this->assertSame( 0, Map::parse_px_dimension( true ), 'A non-scalar-dimension type should resolve to auto.' );
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
		$this->assertSame( '300px', Map::to_css_dimension( 300 ), 'A legacy int should gain a px suffix.' );
		$this->assertSame( '301px', Map::to_css_dimension( 300.6 ), 'A legacy float should round then gain px.' );
		$this->assertSame( '50%', Map::to_css_dimension( '50%' ), 'A style string should pass through.' );
		$this->assertSame( '640px', Map::to_css_dimension( ' 640px ' ), 'A style string should be trimmed.' );
	}

	/**
	 * Style.dimensions px values land as inline width and height.
	 *
	 * With both dimensions explicit there's no auto side, so no
	 * aspect-ratio stamp.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_stamps_style_dimensions_as_inline_styles(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"250px","width":"640px"}}}'
		);

		$this->assertStringContainsString( 'height:250px', $output, 'The style height should stamp inline.' );
		$this->assertStringContainsString( 'width:640px', $output, 'The style width should stamp inline.' );
		$this->assertStringNotContainsString(
			'aspect-ratio',
			$output,
			'With both dimensions explicit there is no auto side to derive.'
		);
	}

	/**
	 * Non-px units apply as CSS; the auto arm still stamps the ratio.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_passes_non_px_units_through_with_aspect_ratio(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"50%"}}}'
		);

		$this->assertStringContainsString( 'height:50%', $output, 'A percent height should stamp as authored.' );
		$this->assertStringContainsString(
			'aspect-ratio:2/1',
			$output,
			'Width is auto, so the ratio should shape the wrapper.'
		);
	}

	/**
	 * Legacy numeric attributes still size the wrapper (pre-0.35 content).
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_falls_back_to_legacy_attributes(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","height":250,"width":640}'
		);

		$this->assertStringContainsString( 'height:250px', $output, 'The legacy height should stamp in px.' );
		$this->assertStringContainsString( 'width:640px', $output, 'The legacy width should stamp in px.' );
		$this->assertStringNotContainsString(
			'aspect-ratio',
			$output,
			'With both dimensions explicit there is no auto side to derive.'
		);
	}

	/**
	 * A style value beats a lingering legacy attribute on the same block.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_prefers_style_value_over_legacy(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","height":300,"style":{"dimensions":{"height":"200px"}}}'
		);

		$this->assertStringContainsString( 'height:200px', $output, 'The style height should win.' );
		$this->assertStringNotContainsString( 'height:300px', $output, 'The legacy height should be ignored.' );
	}

	/**
	 * A dimension value safecss rejects degrades to auto.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_drops_unsafe_dimension_values(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","style":{"dimensions":{"height":"expression(alert(1))"}}}'
		);

		$this->assertStringNotContainsString( 'expression', $output, 'Unsafe CSS should never reach the wrapper.' );
		$this->assertStringContainsString(
			'aspect-ratio:2/1',
			$output,
			'The rejected height should degrade to auto and pick up the ratio.'
		);
	}

	/**
	 * Wide and full alignments own the horizontal space.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_skips_width_for_wide_alignment(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block(
			$venue_id,
			'{"renderMode":"static","align":"wide","style":{"dimensions":{"height":"250px","width":"640px"}}}'
		);

		$this->assertStringNotContainsString(
			'width:640px',
			$output,
			'Wide alignment should suppress the explicit width.'
		);
		$this->assertStringContainsString(
			'aspect-ratio:2/1',
			$output,
			'The alignment-driven width should keep its ratio hint.'
		);
	}

	/**
	 * With no dimensions at all, only the ratio shapes the wrapper.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_uses_ratio_alone_when_no_dimensions_set(): void {
		$venue_id = $this->create_venue_without_coordinates();

		$output = $this->render_block( $venue_id, '{"renderMode":"static"}' );

		$this->assertStringNotContainsString( 'height:', $output, 'No height should stamp when unset.' );
		$this->assertStringContainsString(
			'aspect-ratio:2/1',
			$output,
			'Both sides auto should leave the ratio in charge.'
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
