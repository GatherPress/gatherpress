<?php
/**
 * Class handles the PHP half of the calendar grid parity tests.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Post_Organizer;
use GatherPress\Core\Calendar\Structure_Builder;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Grid_Parity.
 *
 * The editor builds the calendar grid in JS (src/blocks/calendar/utils/grid-builder.js) and the
 * front end builds it in PHP. Both run the fixtures in test/unit/fixtures/calendar-grid and must
 * give the grid each fixture expects, so a change to one side that the other does not make fails.
 * The JS half is test/unit/js/src/blocks/calendar/utils/grid-parity.test.js.
 *
 * @coversNothing
 */
class Test_Grid_Parity extends Base {

	/**
	 * Restore the start of the week.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'start_of_week' );
		parent::tearDown();
	}

	/**
	 * Get the fixtures.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function data_fixtures(): array {
		$fixtures = array();

		foreach ( glob( dirname( __DIR__, 6 ) . '/fixtures/calendar-grid/*.json' ) as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = (string) file_get_contents( $file );

			$fixtures[ basename( $file, '.json' ) ] = array( json_decode( $contents, true ) );
		}

		return $fixtures;
	}

	/**
	 * The PHP grid matches the grid of the fixture.
	 *
	 * @dataProvider data_fixtures
	 *
	 * @param array<string, mixed> $fixture Fixture.
	 *
	 * @return void
	 */
	public function test_grid_matches_fixture( array $fixture ): void {
		update_option( 'start_of_week', $fixture['startOfWeek'] );

		$keys = array();

		foreach ( $fixture['events'] as $event ) {
			if ( 'post' === ( $event['type'] ?? '' ) ) {
				$post_id = $this->mock->post( array( 'post_date' => $event['start'] ) )->get()->ID;

				$keys[ $post_id ] = $event['key'];
				continue;
			}

			$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

			( new Event( $post_id ) )->save_datetimes(
				array(
					'datetime_start' => $event['start'],
					'datetime_end'   => '' !== $event['end'] ? $event['end'] : $event['start'],
					'timezone'       => 'UTC',
				)
			);

			if ( '' === $event['end'] ) {
				delete_post_meta( $post_id, 'gatherpress_datetime_end' );
			}

			if ( ! empty( $event['allDay'] ) ) {
				update_post_meta( $post_id, 'gatherpress_is_all_day', 1 );
			}

			$keys[ $post_id ] = $event['key'];
		}

		$attributes = $fixture['attributes'];
		$date_range = Date_Range::from_attributes( $attributes );
		$time_grid  = Time_Grid::from_attributes(
			array_merge( $attributes, $fixture['week'] ?? array() ),
			array( 'orderBy' => 'datetime' )
		);
		$organized  = ( new Post_Organizer( $date_range, $time_grid ) )->organize( array_keys( $keys ) );
		$calendar   = ( new Structure_Builder( $date_range ) )->build( $organized );
		$units      = array();

		foreach ( $calendar['units'] as $unit ) {
			$units[] = array(
				'weeks'       => array_map(
					function ( array $week ) use ( $keys ): array {
						return array_map(
							function ( array $day ) use ( $keys ) {
								return $this->normalize_day( $day, $keys );
							},
							$week
						);
					},
					$unit['weeks']
				),
				'weekNumbers' => array_map( array( $date_range->week, 'get_number' ), $unit['weeks'] ),
			);
		}

		$actual = array(
			'range' => array(
				'start'     => $date_range->start_date,
				'end'       => $date_range->end_date,
				'unitCount' => $date_range->unit_count,
			),
			'units' => $units,
		);

		if ( $time_grid->enabled ) {
			$actual['timeGrid'] = array(
				'slots'         => $time_grid->slots,
				'totalQuarters' => $time_grid->total_quarters,
			);
		}

		$this->assertSame( $fixture['expected'], $actual );
	}

	/**
	 * Normalize a day of the grid to the shape of the fixtures.
	 *
	 * @param array<string, mixed> $day  Day entry.
	 * @param array<int, string>   $keys Fixture keys of the events, by post ID.
	 *
	 * @return array<string, mixed>|string The day, or 'empty' for a padding cell.
	 */
	private function normalize_day( array $day, array $keys ) {
		if ( ! empty( $day['isEmpty'] ) ) {
			return 'empty';
		}

		$posts = array_map(
			static function ( array $entry ) use ( $keys ): array {
				$post = array(
					'event'     => $keys[ $entry['id'] ],
					'span'      => $entry['span'],
					'dayIndex'  => $entry['day_index'],
					'totalDays' => $entry['total_days'],
					'slot'      => $entry['slot'],
					'allDay'    => $entry['is_all_day'],
				);

				if ( $entry['is_timed'] ) {
					$post['rows']    = array( $entry['time_row_start'], $entry['time_row_end'] );
					$post['overlap'] = array( $entry['overlap_index'], $entry['overlap_total'] );
				}

				return $post;
			},
			$day['posts']
		);

		return array(
			'date'      => $day['date'],
			'isWeekend' => $day['isWeekend'],
			'posts'     => $posts,
		);
	}
}
