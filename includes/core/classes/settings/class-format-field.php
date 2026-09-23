<?php
/**
 * The `format` settings field type.
 *
 * A date or time format chosen from a list of rendered examples, the way
 * `Settings > General` offers the same two values, with a Custom field holding
 * a raw PHP format for anything the list does not cover.
 *
 * Lives beside the Settings class rather than inside it: the sentinel, the
 * companion key and the choice lists are one idea, and `Settings` is already
 * as large as PHPMD will allow.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Utility;

/**
 * Class Format_Field.
 *
 * @since TBD
 */
final class Format_Field {

	/**
	 * The field type this class belongs to.
	 *
	 * @since TBD
	 */
	const TYPE = 'format';

	/**
	 * The value the Custom radio submits.
	 *
	 * A sentinel rather than an empty string because an empty format already
	 * means "fall back to the default" everywhere else in the Settings API, and
	 * because no one is going to save this as a date format on purpose. It is
	 * resolved to the Custom field's own value before anything is stored, so it
	 * never reaches the database.
	 *
	 * @since TBD
	 */
	const CUSTOM = '__gatherpress_custom__';

	/**
	 * The submission key holding a field's Custom value.
	 *
	 * @since TBD
	 *
	 * @param string $option The option key the Custom field belongs to.
	 *
	 * @return string The companion key.
	 */
	public static function custom_key( string $option ): string {
		return $option . '_custom';
	}

	/**
	 * Fold each Custom format back into the option it belongs to.
	 *
	 * A `format` field submits two values: the radio group, and the Custom text
	 * field alongside it. When the radio says Custom the text field is the real
	 * answer; otherwise the text field is whatever the reader last typed there
	 * and is discarded. Either way the companion key is dropped, so nothing
	 * downstream sees it and nothing stores it.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>  $input          The raw submission.
	 * @param array<string, string> $field_type_map Flat map of option_key => field_type.
	 *
	 * @return array<string, mixed> The submission, with each format resolved.
	 */
	public static function resolve( array $input, array $field_type_map ): array {
		foreach ( $field_type_map as $option => $type ) {
			if ( self::TYPE !== $type ) {
				continue;
			}

			$custom_key = self::custom_key( $option );

			if ( ( $input[ $option ] ?? null ) === self::CUSTOM ) {
				$custom           = $input[ $custom_key ] ?? '';
				$input[ $option ] = is_scalar( $custom ) ? (string) $custom : '';
			}

			unset( $input[ $custom_key ] );
		}

		return $input;
	}

	/**
	 * The formats a field offers.
	 *
	 * @since TBD
	 *
	 * @param string $which Which list to offer: 'date' or 'time'.
	 *
	 * @return array<int, array{format: string, example: string}> The choices.
	 */
	public static function choices( string $which ): array {
		return 'time' === $which
			? Utility::time_format_choices()
			: Utility::date_format_choices();
	}
}
