<?php
/**
 * Class handles unit tests for GatherPress\Core\Geocoding\Query.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Geocoding;

use GatherPress\Core\Geocoding\Query;
use GatherPress\Tests\Base;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Geocoding\Query
 */
class Test_Query extends Base {
	/**
	 * Addresses and how they should reach the geocoder.
	 *
	 * Every expected value here was sent to Photon and resolved to the right
	 * place; every unspaced input either matched nothing or matched another
	 * continent.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, string>>
	 */
	public function data_addresses(): array {
		return array(
			// Japanese, unspaced by convention.
			'JA prefecture, ward, block'         => array(
				'東京都港区芝公園4-2-8',
				'東京都 港区 芝公園4-2-8',
			),
			'JA prefecture, city, ward, town'    => array(
				'兵庫県神戸市中央区三宮町3-1-16',
				'兵庫県 神戸市 中央区 三宮町 3-1-16',
			),
			'JA postal mark dropped, rest kept'  => array(
				'〒650-0021 兵庫県神戸市中央区三宮町3-1-16 三星ビル 4階南室',
				'兵庫県 神戸市 中央区 三宮町 3-1-16 三星ビル 4階南室',
			),
			'JA Hokkaido has no suffix to trip'  => array(
				'北海道札幌市中央区北1条西2丁目',
				'北海道 札幌市 中央区 北1条西2丁目',
			),
			// Names containing a suffix character.
			'JA Kyoto is not split at 都'         => array(
				'京都府京都市下京区東塩小路釜殿町',
				'京都府 京都市 下京区 東塩小路 釜殿町',
			),
			'JA Machida is not split at 町'       => array(
				'東京都町田市',
				'東京都 町田市',
			),
			'JA Ichikawa is not split at 市'      => array(
				'千葉県市川市',
				'千葉県 市川市',
			),
			// Chinese, unspaced by convention.
			'ZH municipality, district, road'    => array(
				'北京市朝阳区建国路1号',
				'北京市 朝阳区 建国路 1号',
			),
			'ZH province, city, district, road'  => array(
				'广东省广州市天河区天河路',
				'广东省 广州市 天河区 天河路',
			),
			'ZH Hangzhou is not split at 州'      => array(
				'浙江省杭州市西湖区',
				'浙江省 杭州市 西湖区',
			),
			'ZH municipality with district only' => array(
				'重庆市渝中区',
				'重庆市 渝中区',
			),
			// Korean, normally spaced; only an unspaced paste is touched.
			'KO already spaced is untouched'     => array(
				'서울특별시 중구 세종대로 110',
				'서울특별시 중구 세종대로 110',
			),
			'KO unspaced paste is split'         => array(
				'대구광역시중구동성로2가',
				'대구광역시 중구 동성로 2가',
			),
			'KO Daegu is not split at 구'         => array(
				'대구광역시중구',
				'대구광역시 중구',
			),
			'KO Guro-gu is not split at 로'       => array(
				'서울특별시구로구',
				'서울특별시 구로구',
			),
			// Everything else passes through.
			'Latin address is untouched'         => array(
				'123 Main St, Montclair, NJ 07042',
				'123 Main St, Montclair, NJ 07042',
			),
			'surrounding whitespace is trimmed'  => array(
				"  東京都港区  \n",
				'東京都 港区',
			),
			'empty stays empty'                  => array(
				'',
				'',
			),
			'only a postal mark becomes empty'   => array(
				'〒100-0001',
				'',
			),
		);
	}

	/**
	 * Coverage for normalize.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::normalize
	 * @covers ::split_token
	 *
	 * @dataProvider data_addresses
	 *
	 * @param string $input    Address as typed or pasted.
	 * @param string $expected Address as it should reach the geocoder.
	 *
	 * @return void
	 */
	public function test_normalize( string $input, string $expected ): void {
		$this->assertSame(
			$expected,
			Query::normalize( $input ),
			'Failed to assert the address is split at its administrative boundaries.'
		);
	}

	/**
	 * Bytes that are not UTF-8 pass through untouched.
	 *
	 * With the `u` flag both `preg_replace()` and `preg_split()` refuse
	 * invalid UTF-8, and the geocoder is left to make of it what it was
	 * already making of it before this class existed.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::normalize
	 * @covers ::split_token
	 *
	 * @return void
	 */
	public function test_normalize_leaves_invalid_utf8_alone(): void {
		$bytes = "\xC3\x28\xE6\x9D\xB1";

		$this->assertSame(
			$bytes,
			Query::normalize( $bytes ),
			'Failed to assert bytes the regex engine refuses are passed through unchanged.'
		);
	}

	/**
	 * A site can rewrite the query after the built-in splitting.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::normalize
	 *
	 * @return void
	 */
	public function test_normalize_is_filterable(): void {
		$seen = array();

		add_filter(
			'gatherpress_geocode_query',
			static function ( string $normalized, string $query ) use ( &$seen ): string {
				$seen = array( $normalized, $query );

				return 'rewritten';
			},
			10,
			2
		);

		$this->assertSame(
			'rewritten',
			Query::normalize( '〒100-0001 東京都港区' ),
			'Failed to assert the filter decides what is sent.'
		);
		$this->assertSame(
			array( '東京都 港区', '東京都港区' ),
			$seen,
			'Failed to assert the filter receives both the split and the received address.'
		);
	}
}
