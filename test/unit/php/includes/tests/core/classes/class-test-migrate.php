<?php
/**
 * Class handles unit tests for GatherPress\Core\Migrate.
 *
 * @package GatherPress\Core
 * @since 0.30.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Export;
use GatherPress\Core\Import;
use GatherPress\Core\Migrate;
use GatherPress\Tests\Base;

/**
 * Class Test_Migrate.
 *
 * @coversDefaultClass \GatherPress\Core\Migrate
 * @group migrate
 */
class Test_Migrate extends Base {

	/**
	 * Coverage for get_pseudopostmetas method.
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_get_pseudopostmetas(): void {
		$migrate         = new Migrate();
		$pseudopostmetas = $migrate->get_pseudopostmetas();

		$this->assertIsArray(
			$pseudopostmetas['gatherpress_datetimes'],
			'Failed to assert gatherpress_datetimes is an array'
		);
		$this->assertCount(
			2,
			$pseudopostmetas['gatherpress_datetimes']['export_callback'],
			'Failed to assert export_callback array should have 2 elements.'
		);
		$this->assertSame(
			'GatherPress\Core\Export',
			$pseudopostmetas['gatherpress_datetimes']['export_callback'][0],
			'Failed to assert that class in export_callback does not match.
		'
		);
		$this->assertSame(
			'datetimes_callback',
			$pseudopostmetas['gatherpress_datetimes']['export_callback'][1],
			'Failed to assert method in export_callback does not match.'
		);
		$this->assertCount(
			2,
			$pseudopostmetas['gatherpress_datetimes']['import_callback'],
			'Failed to assert import_callback array should have 2 elements.'
		);
		$this->assertSame(
			'GatherPress\Core\Import',
			$pseudopostmetas['gatherpress_datetimes']['import_callback'][0],
			'Failed to assert that class in import_callback does not match.
		'
		);
		$this->assertSame(
			'datetimes_callback',
			$pseudopostmetas['gatherpress_datetimes']['import_callback'][1],
			'Failed to assert method in import_callback does not match.'
		);
	}

	/**
	 * Coverage for get_pseudopostmetas with gatherpress_pseudo_post_metas filter adding custom metadata.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_get_pseudopostmetas_filter_adds_custom_entry(): void {
		$filter = static function ( array $pseudopostmetas ): array {
			$pseudopostmetas['custom_extension_meta'] = array(
				'export_callback' => static function (): string {
					return 'custom_export_data';
				},
				'import_callback' => static function (): void {},
			);

			return $pseudopostmetas;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter );

		$migrate         = new Migrate();
		$pseudopostmetas = $migrate->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter );

		$this->assertArrayHasKey(
			'custom_extension_meta',
			$pseudopostmetas,
			'Failed to assert custom_extension_meta is present in filtered pseudopostmetas.'
		);
		$this->assertArrayHasKey(
			'gatherpress_datetimes',
			$pseudopostmetas,
			'Failed to assert gatherpress_datetimes remains present alongside custom metadata.'
		);
		$this->assertIsCallable(
			$pseudopostmetas['custom_extension_meta']['export_callback'],
			'Failed to assert custom export callback is callable.'
		);
		$this->assertIsCallable(
			$pseudopostmetas['custom_extension_meta']['import_callback'],
			'Failed to assert custom import callback is callable.'
		);
		$this->assertSame(
			'custom_export_data',
			call_user_func( $pseudopostmetas['custom_extension_meta']['export_callback'] ),
			'Failed to assert custom export callback returns expected data.'
		);
	}

	/**
	 * Coverage for get_pseudopostmetas with gatherpress_pseudo_post_metas filter modifying existing entries.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_get_pseudopostmetas_filter_modifies_existing_entry(): void {
		$custom_export_callback = static function (): string {
			return 'overridden_export';
		};

		$filter = static function ( array $pseudopostmetas ) use ( $custom_export_callback ): array {
			$pseudopostmetas['gatherpress_datetimes']['export_callback'] = $custom_export_callback;

			return $pseudopostmetas;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter );

		$migrate         = new Migrate();
		$pseudopostmetas = $migrate->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter );

		$this->assertSame(
			$custom_export_callback,
			$pseudopostmetas['gatherpress_datetimes']['export_callback'],
			'Failed to assert export_callback was overridden by the filter.'
		);
	}

	/**
	 * Coverage for get_pseudopostmetas with gatherpress_pseudo_post_metas filter removing entries.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_get_pseudopostmetas_filter_removes_entry(): void {
		$filter = static function ( array $pseudopostmetas ): array {
			unset( $pseudopostmetas['gatherpress_datetimes'] );

			return $pseudopostmetas;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter );

		$migrate         = new Migrate();
		$pseudopostmetas = $migrate->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter );

		$this->assertArrayNotHasKey(
			'gatherpress_datetimes',
			$pseudopostmetas,
			'Failed to assert gatherpress_datetimes was removed by filter.'
		);
		$this->assertEmpty(
			$pseudopostmetas,
			'Failed to assert pseudopostmetas is empty when only entry is removed.'
		);
	}

	/**
	 * Coverage for get_pseudopostmetas defensive array casting when filter returns non-array.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_get_pseudopostmetas_handles_non_array_filter_output(): void {
		$filter_null = static function () {
			return null;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter_null );

		$migrate         = new Migrate();
		$pseudopostmetas = $migrate->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter_null );

		$this->assertSame(
			array(),
			$pseudopostmetas,
			'Failed to assert get_pseudopostmetas casts null return from filter to empty array.'
		);

		$filter_false = static function () {
			return false;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter_false );

		$pseudopostmetas = $migrate->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter_false );

		$this->assertSame(
			array( false ),
			$pseudopostmetas,
			'Failed to assert get_pseudopostmetas casts false return from filter to array.'
		);
	}

	/**
	 * Coverage for Export subclass inheriting get_pseudopostmetas.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_export_subclass_inherits_get_pseudopostmetas(): void {
		$export          = Export::get_instance();
		$pseudopostmetas = $export->get_pseudopostmetas();

		$this->assertIsArray( $pseudopostmetas );
		$this->assertArrayHasKey( 'gatherpress_datetimes', $pseudopostmetas );

		$filter = static function ( array $metas ): array {
			$metas['export_test_key'] = array( 'export_callback' => '__return_empty_string' );

			return $metas;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter );

		$filtered_metas = $export->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter );

		$this->assertArrayHasKey(
			'export_test_key',
			$filtered_metas,
			'Failed to assert Export instance reflects gatherpress_pseudo_post_metas filter.'
		);
	}

	/**
	 * Coverage for Import subclass inheriting get_pseudopostmetas.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_import_subclass_inherits_get_pseudopostmetas(): void {
		$import          = Import::get_instance();
		$pseudopostmetas = $import->get_pseudopostmetas();

		$this->assertIsArray( $pseudopostmetas );
		$this->assertArrayHasKey( 'gatherpress_datetimes', $pseudopostmetas );

		$filter = static function ( array $metas ): array {
			$metas['import_test_key'] = array( 'import_callback' => '__return_empty_string' );

			return $metas;
		};

		add_filter( 'gatherpress_pseudo_post_metas', $filter );

		$filtered_metas = $import->get_pseudopostmetas();

		remove_filter( 'gatherpress_pseudo_post_metas', $filter );

		$this->assertArrayHasKey(
			'import_test_key',
			$filtered_metas,
			'Failed to assert Import instance reflects gatherpress_pseudo_post_metas filter.'
		);
	}
}
