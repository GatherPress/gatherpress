<?php
/**
 * Class handles unit tests for GatherPress\Core\Date_Calculator.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Date_Calculator;
use GatherPress\Tests\Base;

/**
 * Class Test_Date_Calculator.
 *
 * @coversDefaultClass \GatherPress\Core\Date_Calculator
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
		$this->assertStringContainsString( 'Pattern is required', $result['message'], 'Failed to assert error message.' );
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
		$this->assertStringContainsString( 'Occurrences must be at least 1', $result['message'], 'Failed to assert error message.' );
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
		$this->assertStringContainsString( 'Occurrences must be at least 1', $result['message'], 'Failed to assert error message.' );
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
		$this->assertStringContainsString( 'Invalid start_date format', $result['message'], 'Failed to assert error message.' );
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
		$this->assertStringContainsString( 'Could not calculate dates for pattern', $result['message'], 'Failed to assert error message.' );
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
}
