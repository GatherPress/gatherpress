<?php
/**
 * Class handles unit tests for GatherPress\Core\Tag_Processor.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Tag_Processor;
use GatherPress\Tests\Base;

/**
 * Class Test_Tag_Processor.
 *
 * @coversDefaultClass \GatherPress\Core\Tag_Processor
 */
class Test_Tag_Processor extends Base {
	/**
	 * Every token, closers included, reports where it starts in the input.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_token_start
	 *
	 * @return void
	 */
	public function test_get_token_start_reports_where_each_tag_begins(): void {
		$html      = '<p><a href="/x">Site</a></p>';
		$processor = new Tag_Processor( $html );
		$starts    = array();

		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$starts[] = $processor->get_token_start();
		}

		$this->assertSame(
			array(
				0,
				strpos( $html, '<a' ),
				strpos( $html, '</a>' ),
				strpos( $html, '</p>' ),
			),
			$starts,
			'Failed to assert each tag reports its own offset.'
		);
	}

	/**
	 * Off a token there is no position to report.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_token_start
	 *
	 * @return void
	 */
	public function test_get_token_start_is_null_off_a_token(): void {
		$processor = new Tag_Processor( '<p>Text</p>' );

		$this->assertNull(
			$processor->get_token_start(),
			'Failed to assert there is no position before parsing starts.'
		);

		while ( $processor->next_tag() ) {
			continue;
		}

		$this->assertNull(
			$processor->get_token_start(),
			'Failed to assert there is no position once parsing is done.'
		);
	}

	/**
	 * Reading a position leaves no bookmark behind, so the bookmark limit is
	 * never reached however often it is read.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_token_start
	 *
	 * @return void
	 */
	public function test_get_token_start_leaves_no_bookmark_behind(): void {
		$processor = new Tag_Processor( '<p>Text</p>' );

		$processor->next_tag();

		for ( $i = 0; $i < 20; $i++ ) {
			$this->assertSame(
				0,
				$processor->get_token_start(),
				'Failed to assert the position can be read repeatedly.'
			);
		}

		$this->assertFalse(
			$processor->has_bookmark( Tag_Processor::BOOKMARK ),
			'Failed to assert the bookmark is released after reading.'
		);
	}
}
