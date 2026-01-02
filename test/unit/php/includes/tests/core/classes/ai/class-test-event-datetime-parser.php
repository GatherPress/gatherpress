<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Event_Datetime_Parser.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use DateTime;
use DateTimeZone;
use Exception;
use GatherPress\Core\AI\Event_Datetime_Parser;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Datetime_Parser.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Event_Datetime_Parser
 */
class Test_Event_Datetime_Parser extends Base {

	/**
	 * Event_Datetime_Parser instance.
	 *
	 * @var Event_Datetime_Parser
	 */
	private $parser;

	/**
	 * Set up test case.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->parser = new Event_Datetime_Parser();
	}

	/**
	 * Test parse_time_only with 12-hour format (3pm).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_pm(): void {
		$result = $this->parser->parse_time_only( '3pm' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with 12-hour format with space (3 pm).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_pm_with_space(): void {
		$result = $this->parser->parse_time_only( '3 pm' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with 12-hour format (3am).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_am(): void {
		$result = $this->parser->parse_time_only( '3am' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 3, $result['hour'], 'Failed to assert hour is 3.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with 12-hour format (12pm).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_noon(): void {
		$result = $this->parser->parse_time_only( '12pm' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 12, $result['hour'], 'Failed to assert hour is 12.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with 12-hour format (12am).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_midnight(): void {
		$result = $this->parser->parse_time_only( '12am' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 0, $result['hour'], 'Failed to assert hour is 0.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with 12-hour format with minutes (3:30pm).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_with_minutes(): void {
		$result = $this->parser->parse_time_only( '3:30pm' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 30, $result['minute'], 'Failed to assert minute is 30.' );
	}

	/**
	 * Test parse_time_only with 12-hour format with minutes and space (3:30 pm).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_12_hour_with_minutes_and_space(): void {
		$result = $this->parser->parse_time_only( '3:30 pm' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 30, $result['minute'], 'Failed to assert minute is 30.' );
	}

	/**
	 * Test parse_time_only with 24-hour format (15:00).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_24_hour(): void {
		$result = $this->parser->parse_time_only( '15:00' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with 24-hour format with minutes (15:30).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_24_hour_with_minutes(): void {
		$result = $this->parser->parse_time_only( '15:30' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 30, $result['minute'], 'Failed to assert minute is 30.' );
	}

	/**
	 * Test parse_time_only with "at" prefix (at 3pm).
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_with_at_prefix(): void {
		$result = $this->parser->parse_time_only( 'at 3pm' );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
		$this->assertEquals( 15, $result['hour'], 'Failed to assert hour is 15.' );
		$this->assertEquals( 0, $result['minute'], 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_time_only with invalid input.
	 *
	 * @covers ::parse_time_only
	 *
	 * @return void
	 */
	public function test_parse_time_only_invalid(): void {
		$result = $this->parser->parse_time_only( 'invalid' );

		$this->assertFalse( $result, 'Failed to assert result is false for invalid input.' );
	}

	/**
	 * Test parse_datetime_input with full datetime format.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_full_datetime(): void {
		$result = $this->parser->parse_datetime_input( '2025-01-04 15:00:00' );

		$this->assertInstanceOf( DateTime::class, $result, 'Failed to assert result is DateTime instance.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 15:00:00', $result->format( 'Y-m-d H:i:s' ), 'Failed to assert datetime matches.' );
	}

	/**
	 * Test parse_datetime_input with date-only format.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_date_only(): void {
		$result = $this->parser->parse_datetime_input( '2025-01-04' );

		$this->assertInstanceOf( DateTime::class, $result, 'Failed to assert result is DateTime instance.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 12:00:00', $result->format( 'Y-m-d H:i:s' ), 'Failed to assert datetime defaults to noon.' );
	}

	/**
	 * Test parse_datetime_input with time-only and existing date.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_time_only_with_existing_date(): void {
		$result = $this->parser->parse_datetime_input( '3pm', '2025-01-04' );

		$this->assertInstanceOf( DateTime::class, $result, 'Failed to assert result is DateTime instance.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 15:00:00', $result->format( 'Y-m-d H:i:s' ), 'Failed to assert datetime merges time with existing date.' );
	}

	/**
	 * Test parse_datetime_input with time-only without existing date.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_time_only_without_existing_date(): void {
		$result = $this->parser->parse_datetime_input( '3pm' );

		$this->assertInstanceOf( DateTime::class, $result, 'Failed to assert result is DateTime instance.' );
		$this->assertEquals( 15, (int) $result->format( 'H' ), 'Failed to assert hour is 15.' );
		$this->assertEquals( 0, (int) $result->format( 'i' ), 'Failed to assert minute is 0.' );
	}

	/**
	 * Test parse_datetime_input with custom timezone.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_with_timezone(): void {
		$result = $this->parser->parse_datetime_input( '2025-01-04 15:00:00', null, 'America/New_York' );

		$this->assertInstanceOf( DateTime::class, $result, 'Failed to assert result is DateTime instance.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( 'America/New_York', $result->getTimezone()->getName(), 'Failed to assert timezone is set.' );
	}

	/**
	 * Test parse_datetime_input with invalid input throws exception.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_invalid_throws_exception(): void {
		$this->expectException( Exception::class );
		$this->parser->parse_datetime_input( 'completely invalid input' );
	}

	/**
	 * Test prepare_datetime_params with only start datetime.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_only_start(): void {
		$new_datetimes     = array(
			'datetime_start' => '2025-01-04 15:00:00',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present (recalculated).' );
		$this->assertEquals( '2025-01-04 15:00:00', $result['datetime_start'], 'Failed to assert new start datetime.' );
		// End time should be recalculated (start + 2 hours), not preserved.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 17:00:00', $result['datetime_end'], 'Failed to assert end datetime was recalculated (start + 2 hours), not preserved.' );
	}

	/**
	 * Test prepare_datetime_params with only end datetime.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_only_end(): void {
		$new_datetimes     = array(
			'datetime_end' => '2025-01-04 17:00:00',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present (preserved from existing).' );
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 12:00:00', $result['datetime_start'], 'Failed to assert existing start datetime is preserved.' );
		$this->assertEquals( '2025-01-04 17:00:00', $result['datetime_end'], 'Failed to assert new end datetime.' );
	}

	/**
	 * Test prepare_datetime_params with time-only start and existing date.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_time_only_start_with_existing_date(): void {
		$new_datetimes     = array(
			'datetime_start' => '3pm',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 15:00:00', $result['datetime_start'], 'Failed to assert time-only merges with existing date.' );
		// End time should be recalculated (start + 2 hours), not preserved.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 17:00:00', $result['datetime_end'], 'Failed to assert end datetime was recalculated (start + 2 hours), not preserved.' );
	}

	/**
	 * Test prepare_datetime_params with time-only end and existing date.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_time_only_end_with_existing_date(): void {
		$new_datetimes     = array(
			'datetime_end' => '5pm',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 17:00:00', $result['datetime_end'], 'Failed to assert time-only merges with existing date.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 12:00:00', $result['datetime_start'], 'Failed to assert existing start datetime is preserved.' );
	}

	/**
	 * Test prepare_datetime_params with only start and no existing end (should default end).
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_only_start_no_existing_end(): void {
		$new_datetimes     = array(
			'datetime_start' => '2025-01-04 15:00:00',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present (defaulted).' );
		$this->assertEquals( '2025-01-04 15:00:00', $result['datetime_start'], 'Failed to assert new start datetime.' );
		$this->assertEquals(
			'2025-01-04 17:00:00',
			$result['datetime_end'],
			'Failed to assert end datetime defaults to start + 2 hours.'
		);
	}

	/**
	 * Test prepare_datetime_params with only end and no existing start (should default start).
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_only_end_no_existing_start(): void {
		$new_datetimes     = array(
			'datetime_end' => '2025-01-04 17:00:00',
		);
		$existing_datetime = array(
			'datetime_end'     => '2025-01-04 14:00:00',
			'datetime_end_gmt' => '2025-01-04 14:00:00',
			'timezone'         => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present (defaulted).' );
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 15:00:00', $result['datetime_start'], 'Failed to assert start datetime defaults to end - 2 hours.' );
		$this->assertEquals( '2025-01-04 17:00:00', $result['datetime_end'], 'Failed to assert new end datetime.' );
	}

	/**
	 * Test prepare_datetime_params with both start and end.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_both_start_and_end(): void {
		$new_datetimes     = array(
			'datetime_start' => '2025-01-04 15:00:00',
			'datetime_end'   => '2025-01-04 17:00:00',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present.' );
		$this->assertEquals( '2025-01-04 15:00:00', $result['datetime_start'], 'Failed to assert new start datetime.' );
		$this->assertEquals( '2025-01-04 17:00:00', $result['datetime_end'], 'Failed to assert new end datetime.' );
	}

	/**
	 * Test prepare_datetime_params with custom timezone.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_with_custom_timezone(): void {
		$new_datetimes     = array(
			'datetime_start' => '2025-01-04 15:00:00',
			'timezone'       => 'America/New_York',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertEquals( 'America/New_York', $result['timezone'], 'Failed to assert custom timezone is used.' );
	}

	/**
	 * Test prepare_datetime_params validates end is after start.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_validates_end_after_start(): void {
		$new_datetimes     = array(
			'datetime_start' => '2025-01-04 17:00:00',
			'datetime_end'   => '2025-01-04 15:00:00',
		);
		$existing_datetime = array(
			'timezone' => 'UTC',
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'End datetime must be after start datetime.' );
		$this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );
	}

	/**
	 * Test prepare_datetime_params with time-only start and no existing datetime.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_time_only_start_no_existing(): void {
		$new_datetimes     = array(
			'datetime_start' => '3pm',
		);
		$existing_datetime = array(
			'timezone' => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present (defaulted).' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( 15, (int) DateTime::createFromFormat( 'Y-m-d H:i:s', $result['datetime_start'] )->format( 'H' ), 'Failed to assert start hour is 15.' );
	}

	/**
	 * Test prepare_datetime_params preserves existing timezone when not provided.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_preserves_existing_timezone(): void {
		$new_datetimes     = array(
			'datetime_start' => '2025-01-04 15:00:00',
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-04 12:00:00',
			'datetime_start_gmt' => '2025-01-04 12:00:00',
			'datetime_end'       => '2025-01-04 14:00:00',
			'datetime_end_gmt'   => '2025-01-04 14:00:00',
			'timezone'           => 'America/Los_Angeles',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( 'America/Los_Angeles', $result['timezone'], 'Failed to assert existing timezone is preserved.' );
	}

	/**
	 * Test parse_datetime_input preserves existing date when OpenAI provides today's date.
	 *
	 * @covers ::parse_datetime_input
	 *
	 * @return void
	 */
	public function test_parse_datetime_input_preserves_existing_date_when_today_provided(): void {
		$today         = new DateTime( 'now' );
		$today_str     = $today->format( 'Y-m-d' );
		$existing_date = '2025-01-05'; // Different from today.
		$time_str      = '15:00:00';
		$input         = $today_str . ' ' . $time_str;

		$result = $this->parser->parse_datetime_input( $input, $existing_date );

		$this->assertInstanceOf( DateTime::class, $result, 'Failed to assert result is DateTime instance.' );
		// Should preserve existing date (2025-01-05) and use time from input (15:00:00).
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-05 15:00:00', $result->format( 'Y-m-d H:i:s' ), 'Failed to assert existing date is preserved with time from input.' );
	}

	/**
	 * Test prepare_datetime_params uses GMT datetime when local datetime is missing.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_uses_gmt_when_local_missing(): void {
		$new_datetimes     = array(
			'datetime_start' => '3pm',
		);
		$existing_datetime = array(
			// No local datetime_start, but GMT exists.
			'datetime_start_gmt' => '2025-01-05 12:00:00',
			'datetime_end_gmt'   => '2025-01-05 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-05 15:00:00', $result['datetime_start'], 'Failed to assert time-only merges with date extracted from GMT.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present (recalculated).' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-05 17:00:00', $result['datetime_end'], 'Failed to assert end datetime is recalculated (start + 2 hours).' );
	}

	/**
	 * Test prepare_datetime_params preserves existing start when only in GMT format.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_preserves_gmt_start_when_updating_end(): void {
		$new_datetimes     = array(
			'datetime_end' => '2pm',
		);
		$existing_datetime = array(
			// Start only exists in GMT, not local.
			'datetime_start_gmt' => '2025-01-06 09:00:00',
			'datetime_end'       => '2025-01-06 11:00:00',
			'datetime_end_gmt'   => '2025-01-06 11:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is preserved from GMT.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-06 09:00:00', $result['datetime_start'], 'Failed to assert start time preserved from GMT.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-06 14:00:00', $result['datetime_end'], 'Failed to assert end time updated to 2pm.' );
	}

	/**
	 * Test prepare_datetime_params converts GMT to local when both are only in GMT.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_converts_gmt_to_local_when_only_gmt_exists(): void {
		$new_datetimes     = array(
			'datetime_end' => '2pm',
		);
		$existing_datetime = array(
			// Both start and end only exist in GMT, not local.
			'datetime_start_gmt' => '2025-01-06 09:00:00',
			'datetime_end_gmt'   => '2025-01-06 11:00:00',
			'timezone'           => 'America/New_York',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start converted from GMT.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present.' );
		// Start should be converted from GMT to local timezone.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringStartsWith( '2025-01-06', $result['datetime_start'], 'Failed to assert start date preserved.' );
		// End should be updated to 2pm in local timezone.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( 14, (int) DateTime::createFromFormat( 'Y-m-d H:i:s', $result['datetime_end'] )->format( 'H' ), 'Failed to assert end time updated to 2pm.' );
	}

	/**
	 * Test prepare_datetime_params recalculates end when updating start time only.
	 *
	 * @covers ::prepare_datetime_params
	 *
	 * @return void
	 */
	public function test_prepare_datetime_params_recalculates_end_when_updating_start_only(): void {
		$new_datetimes     = array(
			'datetime_start' => '3pm', // Time-only update.
		);
		$existing_datetime = array(
			'datetime_start'     => '2025-01-05 12:00:00',
			'datetime_start_gmt' => '2025-01-05 12:00:00',
			'datetime_end'       => '2025-01-05 14:00:00', // Existing end should be recalculated.
			'datetime_end_gmt'   => '2025-01-05 14:00:00',
			'timezone'           => 'UTC',
		);

		$result = $this->parser->prepare_datetime_params( $new_datetimes, $existing_datetime );

		$this->assertArrayHasKey( 'datetime_start', $result, 'Failed to assert datetime_start is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-05 15:00:00', $result['datetime_start'], 'Failed to assert start time is updated.' );
		// End should be recalculated to start + 2 hours, not preserved.
		$this->assertArrayHasKey( 'datetime_end', $result, 'Failed to assert datetime_end is present.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-05 17:00:00', $result['datetime_end'], 'Failed to assert end datetime is recalculated (start + 2 hours), not preserved.' );
	}
}
