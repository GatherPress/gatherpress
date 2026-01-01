<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Date_Calculator.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Date_Calculator;
use GatherPress\Tests\Base;

/**
 * Class Test_Date_Calculator.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Date_Calculator
 */
class Test_Date_Calculator extends Base {

	/**
	 * Coverage for calculate_dates method with valid Nth weekday pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_nth_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => '3rd Tuesday',
			'occurrences' => 3,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'dates', $result['data'], 'Failed to assert dates key exists.' );
		$this->assertCount( 3, $result['data']['dates'], 'Failed to assert 3 dates returned.' );
		$this->assertSame( '3rd Tuesday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
		$this->assertSame( 3, $result['data']['count'], 'Failed to assert count is 3.' );
	}

	/**
	 * Coverage for calculate_dates method with "every weekday" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every Monday',
			'occurrences' => 4,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'dates', $result['data'], 'Failed to assert dates key exists.' );
		$this->assertCount( 4, $result['data']['dates'], 'Failed to assert 4 dates returned.' );
		$this->assertSame( 'every Monday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "last weekday" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_last_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'last Friday',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'dates', $result['data'], 'Failed to assert dates key exists.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'last Friday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method without pattern parameter.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_without_pattern(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'occurrences' => 3,
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Pattern is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}

	/**
	 * Coverage for calculate_dates method without occurrences parameter.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_without_occurrences(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern' => 'every Monday',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString(
			'Occurrences must be at least 1',
			$result['message'],
			'Failed to assert error message.'
		);
	}

	/**
	 * Coverage for calculate_dates method with zero occurrences.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_zero_occurrences(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every Monday',
			'occurrences' => 0,
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString(
			'Occurrences must be at least 1',
			$result['message'],
			'Failed to assert error message.'
		);
	}

	/**
	 * Coverage for calculate_dates method with invalid start_date format.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_invalid_start_date(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every Monday',
			'occurrences' => 3,
			'start_date'  => 'invalid-date',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString(
			'Invalid start_date format',
			$result['message'],
			'Failed to assert error message.'
		);
	}

	/**
	 * Coverage for calculate_dates method with unrecognized pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_unrecognized_pattern(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'invalid pattern',
			'occurrences' => 3,
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString(
			'Could not calculate dates for pattern',
			$result['message'],
			'Failed to assert error message.'
		);
	}

	/**
	 * Coverage for calculate_dates method with default start_date.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_default_start_date(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every Monday',
			'occurrences' => 2,
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'dates', $result['data'], 'Failed to assert dates key exists.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
	}

	/**
	 * Coverage for calculate_dates method with different ordinal patterns.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_different_ordinals(): void {
		$calculator = new Date_Calculator();
		$patterns   = array( '1st Monday', '2nd Tuesday', '3rd Wednesday', '4th Thursday', '5th Friday' );

		foreach ( $patterns as $pattern ) {
			$params = array(
				'pattern'     => $pattern,
				'occurrences' => 1,
				'start_date'  => '2025-01-01',
			);
			$result = $calculator->calculate_dates( $params );

			$this->assertTrue( $result['success'], "Failed to assert success for pattern: {$pattern}" );
			$this->assertCount( 1, $result['data']['dates'], "Failed to assert 1 date for pattern: {$pattern}" );
		}
	}

	/**
	 * Coverage for calculate_dates method with all weekdays.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_all_weekdays(): void {
		$calculator = new Date_Calculator();
		$weekdays   = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

		foreach ( $weekdays as $weekday ) {
			$params = array(
				'pattern'     => "every {$weekday}",
				'occurrences' => 1,
				'start_date'  => '2025-01-01',
			);
			$result = $calculator->calculate_dates( $params );

			$this->assertTrue( $result['success'], "Failed to assert success for weekday: {$weekday}" );
			$this->assertCount( 1, $result['data']['dates'], "Failed to assert 1 date for weekday: {$weekday}" );
		}
	}

	/**
	 * Coverage for calculate_dates method with "next weekday" patterns.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_next_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'next Tuesday',
			'occurrences' => 2,
			'start_date'  => '2025-01-01', // Wednesday.
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for next Tuesday.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'next Tuesday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "last weekday" patterns.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_last_weekday_pattern(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'last Friday',
			'occurrences' => 1,
			'start_date'  => '2025-01-01', // Wednesday.
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for last Friday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame( 'last Friday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "this weekday" patterns.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_this_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'this Monday',
			'occurrences' => 1,
			'start_date'  => '2025-01-01', // Wednesday.
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for this Monday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame( 'this Monday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "tomorrow" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_tomorrow(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'tomorrow',
			'occurrences' => 3,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for tomorrow.' );
		$this->assertCount( 3, $result['data']['dates'], 'Failed to assert 3 dates returned.' );
		$this->assertSame( 'tomorrow', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "yesterday" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_yesterday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'yesterday',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for yesterday.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'yesterday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "next week" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_next_week(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'next week',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for next week.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'next week', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "last month" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_last_month(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'last month',
			'occurrences' => 1,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for last month.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame( 'last month', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "in X days" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_in_days(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'in 5 days',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for in 5 days.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'in 5 days', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "X days ago" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_days_ago(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => '3 days ago',
			'occurrences' => 1,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for 3 days ago.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame( '3 days ago', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "every other weekday" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_other_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every other Wednesday',
			'occurrences' => 3,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every other Wednesday.' );
		$this->assertCount( 3, $result['data']['dates'], 'Failed to assert 3 dates returned.' );
		$this->assertSame( 'every other Wednesday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "every X weeks" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_weeks(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every 2 weeks',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every 2 weeks.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'every 2 weeks', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Coverage for calculate_dates method with "every X months" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_months(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every 3 months',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every 3 months.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( 'every 3 months', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Test calculate_dates with "X weeks from weekday" pattern.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_weeks_from_weekday(): void {
		$calculator = new Date_Calculator();

		// Test "2 weeks from Thursday" starting from Monday, October 27, 2025.
		$params = array(
			'pattern'     => '2 weeks from Thursday',
			'occurrences' => 1,
			'start_date'  => '2025-10-27',
		);

		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for 2 weeks from Thursday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame(
			'2025-11-13',
			$result['data']['dates'][0],
			'Failed to assert correct date for 2 weeks from Thursday.'
		);
		$this->assertSame( '2 weeks from Thursday', $result['data']['pattern'], 'Failed to assert pattern matches.' );

		// Test "3 weeks from Thursday" starting from Monday, October 27, 2025.
		$params = array(
			'pattern'     => '3 weeks from Thursday',
			'occurrences' => 1,
			'start_date'  => '2025-10-27',
		);

		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for 3 weeks from Thursday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame(
			'2025-11-20',
			$result['data']['dates'][0],
			'Failed to assert correct date for 3 weeks from Thursday.'
		);
		$this->assertSame( '3 weeks from Thursday', $result['data']['pattern'], 'Failed to assert pattern matches.' );
	}

	/**
	 * Test calculate_dates with "after X days" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_after_days(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'after 7 days',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for after 7 days.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
	}

	/**
	 * Test calculate_dates with "X days before" pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_days_before(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => '5 days before',
			'occurrences' => 1,
			'start_date'  => '2025-01-10',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for 5 days before.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
	}

	/**
	 * Test calculate_dates with different ordinal formats (first, second, etc).
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_ordinal_formats(): void {
		$calculator = new Date_Calculator();
		$ordinals   = array( 'first', 'second', 'third', 'fourth', '1st', '2nd', '3rd', '4th' );

		foreach ( $ordinals as $ordinal ) {
			$params = array(
				'pattern'     => "{$ordinal} Monday",
				'occurrences' => 1,
				'start_date'  => '2025-01-01',
			);
			$result = $calculator->calculate_dates( $params );

			$this->assertTrue( $result['success'], "Failed to assert success for {$ordinal} Monday." );
			$this->assertCount( 1, $result['data']['dates'], "Failed to assert 1 date for {$ordinal} Monday." );
		}
	}

	/**
	 * Test calculate_dates with date before start_date (should move to next month).
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_before_start_date(): void {
		$calculator = new Date_Calculator();
		// Start on Jan 15, but 1st Tuesday of Jan is Jan 7, which is before start_date.
		$params = array(
			'pattern'     => '1st Tuesday',
			'occurrences' => 2,
			'start_date'  => '2025-01-15',
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success when date is before start_date.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		// First date should be in February, not January.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringStartsWith( '2025-02', $result['data']['dates'][0], 'Failed to assert date moved to next month.' );
	}

	/**
	 * Test calculate_dates with "every X week" (singular) pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_week_singular(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every 2 week',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every 2 week.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
	}

	/**
	 * Test calculate_dates with "every X month" (singular) pattern.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_month_singular(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'every 3 month',
			'occurrences' => 2,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every 3 month.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
	}

	/**
	 * Test calculate_dates with "this weekday" when current day is before weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_this_weekday_before(): void {
		$calculator = new Date_Calculator();
		// Start on Monday (2025-01-06), test for Wednesday (should be 2 days ahead).
		$params = array(
			'pattern'     => 'this Wednesday',
			'occurrences' => 1,
			'start_date'  => '2025-01-06', // Monday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for this Wednesday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame( '2025-01-08', $result['data']['dates'][0], 'Failed to assert correct date.' );
	}

	/**
	 * Test calculate_dates with "this weekday" when current day is after weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_this_weekday_after(): void {
		$calculator = new Date_Calculator();
		// Start on Friday (2025-01-03), test for Monday (should be 3 days ahead, next week).
		$params = array(
			'pattern'     => 'this Monday',
			'occurrences' => 1,
			'start_date'  => '2025-01-03', // Friday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for this Monday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
		$this->assertSame( '2025-01-06', $result['data']['dates'][0], 'Failed to assert correct date.' );
	}

	/**
	 * Test calculate_dates with "last weekday" when current day is before weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_last_weekday_before(): void {
		$calculator = new Date_Calculator();
		// Start on Monday (2025-01-06), test for last Friday (should go back to previous Friday).
		$params = array(
			'pattern'     => 'last Friday',
			'occurrences' => 1,
			'start_date'  => '2025-01-06', // Monday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for last Friday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
	}

	/**
	 * Test calculate_dates with "last weekday" when current day is after weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_last_weekday_after(): void {
		$calculator = new Date_Calculator();
		// Start on Friday (2025-01-03), test for last Monday (should go back to previous Monday).
		$params = array(
			'pattern'     => 'last Monday',
			'occurrences' => 1,
			'start_date'  => '2025-01-03', // Friday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for last Monday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
	}

	/**
	 * Test calculate_dates with "next weekday" multiple occurrences.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_next_weekday_multiple(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => 'next Thursday',
			'occurrences' => 4,
			'start_date'  => '2025-01-01', // Wednesday.
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for next Thursday.' );
		$this->assertCount( 4, $result['data']['dates'], 'Failed to assert 4 dates returned.' );
		// Verify dates are 7 days apart.
		$date1 = new \DateTime( $result['data']['dates'][0] );
		$date2 = new \DateTime( $result['data']['dates'][1] );
		$diff  = $date1->diff( $date2 );
		$this->assertSame( 7, (int) $diff->format( '%a' ), 'Failed to assert dates are 7 days apart.' );
	}

	/**
	 * Test calculate_dates with "every weekday" when current day matches weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_weekday_same_day(): void {
		$calculator = new Date_Calculator();
		// Start on Monday (2025-01-06), test for every Monday.
		$params = array(
			'pattern'     => 'every Monday',
			'occurrences' => 3,
			'start_date'  => '2025-01-06', // Monday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every Monday.' );
		$this->assertCount( 3, $result['data']['dates'], 'Failed to assert 3 dates returned.' );
		$this->assertSame( '2025-01-06', $result['data']['dates'][0], 'Failed to assert first date is start date.' );
	}

	/**
	 * Test calculate_dates with "every weekday" when current day is before weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_weekday_before(): void {
		$calculator = new Date_Calculator();
		// Start on Monday (2025-01-06), test for every Wednesday.
		$params = array(
			'pattern'     => 'every Wednesday',
			'occurrences' => 2,
			'start_date'  => '2025-01-06', // Monday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every Wednesday.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( '2025-01-08', $result['data']['dates'][0], 'Failed to assert correct first date.' );
	}

	/**
	 * Test calculate_dates with "every weekday" when current day is after weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_every_weekday_after(): void {
		$calculator = new Date_Calculator();
		// Start on Friday (2025-01-03), test for every Monday.
		$params = array(
			'pattern'     => 'every Monday',
			'occurrences' => 2,
			'start_date'  => '2025-01-03', // Friday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every Monday.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
		$this->assertSame( '2025-01-06', $result['data']['dates'][0], 'Failed to assert correct first date.' );
	}

	/**
	 * Test calculate_dates with biweekly pattern when current day matches weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_biweekly_same_day(): void {
		$calculator = new Date_Calculator();
		// Start on Monday (2025-01-06), test for every other Monday.
		$params = array(
			'pattern'     => 'every other Monday',
			'occurrences' => 3,
			'start_date'  => '2025-01-06', // Monday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every other Monday.' );
		$this->assertCount( 3, $result['data']['dates'], 'Failed to assert 3 dates returned.' );
		// Verify dates are 14 days apart.
		$date1 = new \DateTime( $result['data']['dates'][0] );
		$date2 = new \DateTime( $result['data']['dates'][1] );
		$diff  = $date1->diff( $date2 );
		$this->assertSame( 14, (int) $diff->format( '%a' ), 'Failed to assert dates are 14 days apart.' );
	}

	/**
	 * Test calculate_dates with biweekly pattern when current day is before weekday.
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_biweekly_before(): void {
		$calculator = new Date_Calculator();
		// Start on Monday (2025-01-06), test for every other Wednesday.
		$params = array(
			'pattern'     => 'every other Wednesday',
			'occurrences' => 2,
			'start_date'  => '2025-01-06', // Monday.
		);
		$result = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for every other Wednesday.' );
		$this->assertCount( 2, $result['data']['dates'], 'Failed to assert 2 dates returned.' );
	}

	/**
	 * Test calculate_dates with "last" ordinal (5th occurrence).
	 *
	 * @covers ::calculate_dates
	 *
	 * @return void
	 */
	public function test_calculate_dates_fifth_weekday(): void {
		$calculator = new Date_Calculator();
		$params     = array(
			'pattern'     => '5th Friday',
			'occurrences' => 1,
			'start_date'  => '2025-01-01',
		);
		$result     = $calculator->calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success for 5th Friday.' );
		$this->assertCount( 1, $result['data']['dates'], 'Failed to assert 1 date returned.' );
	}
}
