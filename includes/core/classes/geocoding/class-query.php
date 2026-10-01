<?php
/**
 * Prepares an address for the geocoder.
 *
 * Japanese and Chinese addresses are written without spaces, and Korean ones
 * sometimes arrive that way when pasted. Photon tokenizes on whitespace, so
 * such an address reaches it as a single token and either matches nothing or
 * matches the wrong continent. Inserting spaces at the administrative
 * boundaries is enough for it to resolve, and to resolve to the right
 * building rather than the right district.
 *
 * @package GatherPress\Core\Geocoding
 * @since 0.36.0
 */

namespace GatherPress\Core\Geocoding;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Query.
 *
 * Splits an unspaced East Asian address into its administrative units.
 *
 * @since 0.36.0
 */
class Query {
	/**
	 * Top-level units, matched by exact prefix.
	 *
	 * Matched from a list rather than by suffix because the suffix characters
	 * also occur inside names: 京都市 contains 都 and 大구 contains 구, and a
	 * split there sends the geocoder a query that matches nothing.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	private const TOP_LEVEL = array(
		// Japan: the 47 prefectures.
		'北海道',
		'青森県',
		'岩手県',
		'宮城県',
		'秋田県',
		'山形県',
		'福島県',
		'茨城県',
		'栃木県',
		'群馬県',
		'埼玉県',
		'千葉県',
		'東京都',
		'神奈川県',
		'新潟県',
		'富山県',
		'石川県',
		'福井県',
		'山梨県',
		'長野県',
		'岐阜県',
		'静岡県',
		'愛知県',
		'三重県',
		'滋賀県',
		'京都府',
		'大阪府',
		'兵庫県',
		'奈良県',
		'和歌山県',
		'鳥取県',
		'島根県',
		'岡山県',
		'広島県',
		'山口県',
		'徳島県',
		'香川県',
		'愛媛県',
		'高知県',
		'福岡県',
		'佐賀県',
		'長崎県',
		'熊本県',
		'大分県',
		'宮崎県',
		'鹿児島県',
		'沖縄県',
		// China: provinces, municipalities, autonomous and special regions.
		'北京市',
		'天津市',
		'上海市',
		'重庆市',
		'河北省',
		'山西省',
		'辽宁省',
		'吉林省',
		'黑龙江省',
		'江苏省',
		'浙江省',
		'安徽省',
		'福建省',
		'江西省',
		'山东省',
		'河南省',
		'湖北省',
		'湖南省',
		'广东省',
		'海南省',
		'四川省',
		'贵州省',
		'云南省',
		'陕西省',
		'甘肃省',
		'青海省',
		'内蒙古自治区',
		'广西壮族自治区',
		'西藏自治区',
		'宁夏回族自治区',
		'新疆维吾尔自治区',
		'香港特别行政区',
		'澳门特别行政区',
		// Taiwan, in the traditional script its addresses use.
		'台北市',
		'臺北市',
		'新北市',
		'桃園市',
		'台中市',
		'臺中市',
		'台南市',
		'臺南市',
		'高雄市',
		// Korea: metropolitan cities and provinces, current and former names.
		'서울특별시',
		'부산광역시',
		'대구광역시',
		'인천광역시',
		'광주광역시',
		'대전광역시',
		'울산광역시',
		'세종특별자치시',
		'경기도',
		'강원특별자치도',
		'강원도',
		'충청북도',
		'충청남도',
		'전북특별자치도',
		'전라북도',
		'전라남도',
		'경상북도',
		'경상남도',
		'제주특별자치도',
	);

	/**
	 * Suffixes closing a city, ward, county or district.
	 *
	 * Japanese and Chinese share the Han characters; 縣 is the traditional
	 * form of 县 used in Taiwan. 州 is deliberately absent: it closes a few
	 * autonomous prefectures but sits inside 广州, 杭州 and 苏州.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	private const CITY_SUFFIXES = '市区郡县縣镇乡시군구';

	/**
	 * Suffixes closing a town, neighborhood, road or block.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	private const STREET_SUFFIXES = '町村路街道巷弄동읍면로길';

	/**
	 * Insert spaces at the administrative boundaries of an address.
	 *
	 * Only tokens without whitespace are touched, so an address the author
	 * already separated, which is how Korean is normally written, is left as
	 * it is. Nothing is removed or reordered except a Japanese postal mark,
	 * which the geocoder cannot use.
	 *
	 * @since 0.36.0
	 *
	 * @param string $query Address as typed or pasted.
	 *
	 * @return string The address with its units separated by spaces.
	 */
	public static function normalize( string $query ): string {
		$query = trim( preg_replace( '/〒\s*\d{3}-?\d{4}\s*/u', '', $query ) ?? $query );

		if ( '' === $query ) {
			return '';
		}

		$tokens = preg_split( '/\s+/u', $query );

		if ( false === $tokens ) {
			$tokens = array( $query );
		}

		$units = array_map(
			static function ( string $token ): array {
				return self::split_token( $token );
			},
			$tokens
		);

		$normalized = implode( ' ', array_merge( ...$units ) );

		/**
		 * Filters the address sent to the geocoder.
		 *
		 * Runs after the built-in splitting. Use it to add boundaries for a
		 * script the plugin does not know, or to rewrite a local address
		 * convention the geocoder trips over.
		 *
		 * @since 0.36.0
		 *
		 * @param string $normalized Address as it will be sent.
		 * @param string $query      Address as received, postal mark removed.
		 *
		 * @return string
		 */
		return (string) apply_filters( 'gatherpress_geocode_query', $normalized, $query );
	}

	/**
	 * Split one unspaced token into its units.
	 *
	 * @since 0.36.0
	 *
	 * @param string $token A run of characters with no whitespace.
	 *
	 * @return string[] The units, in their original order.
	 */
	private static function split_token( string $token ): array {
		if ( 1 !== preg_match( '/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $token ) ) {
			return array( $token );
		}

		$units = array();

		foreach ( self::TOP_LEVEL as $unit ) {
			if ( str_starts_with( $token, $unit ) && mb_strlen( $token ) > mb_strlen( $unit ) ) {
				$units[] = $unit;
				$token   = mb_substr( $token, mb_strlen( $unit ) );
				break;
			}
		}

		foreach ( array( self::CITY_SUFFIXES, self::STREET_SUFFIXES ) as $suffixes ) {
			$pattern = '/^(.+?[' . $suffixes . '])/u';

			// Shortest match first, so 市川市 is one unit rather than 市 and
			// 川市, and a trailing unit is taken whole so 구로구 does not fall
			// through to the street pass and split on the 로 inside it.
			while ( 1 === preg_match( $pattern, $token, $matches ) ) {
				$units[] = $matches[1];
				$token   = mb_substr( $token, mb_strlen( $matches[1] ) );
			}
		}

		if ( '' !== $token ) {
			$units[] = $token;
		}

		return $units;
	}
}
