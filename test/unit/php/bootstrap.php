<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 0.27.0
 */

// phpcs:disable Squiz.Commenting.FileComment.Missing

// Refuse to run against a stale bundle. First statement of the bootstrap on
// purpose: this has to fail before WordPress loads, not as a puzzling
// block-render assertion two minutes into the suite.
require_once __DIR__ . '/build-freshness.php';

// Record every query so the suite's query-capture assertions have something to
// read. Several tests slice `$wpdb->queries` to prove a code path issues the
// SQL it claims to. With SAVEQUERIES off that property stays null and those
// tests error on `count( null )` instead of asserting. This has to happen
// before `Bootstrap::start()` loads wp-config and boots `$wpdb`, and it is
// guarded so an environment that already turns query recording on (a wp-env
// override, a CI config, or the WordPress test config) keeps its own value.
// Applies to the single-site and multisite runs alike, because both configs
// load this same bootstrap.
if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

// Enable WP-CLI stub so CLI code paths are covered in tests.
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

// Shared test fixtures. The autoloader maps `class-*.php` only, and PHPUnit
// collects `class-test-*.php` only, so trait files are required by hand.
require_once __DIR__ . '/includes/tests/core/classes/event/recurrence/trait-occurrence-fixtures.php';
require_once __DIR__ . '/includes/tests/core/classes/event/recurrence/trait-rewrite-state.php';

$gatherpress_bootstrap_instance = PMC\Unit_Test\Bootstrap::get_instance();

tests_add_filter(
	'plugins_loaded',
	static function () {
		// Manually load our plugin without having to setup the development folder in the correct plugin folder.
		require_once __DIR__ . '/../../../gatherpress.php';
	}
);

tests_add_filter(
	'gatherpress_autoloader',
	static function ( array $namespaces ): array {
		$namespaces['GatherPress\Tests'] = __DIR__;

		return $namespaces;
	}
);

$gatherpress_bootstrap_instance->start();

// Create the plugin's custom tables once, here, before the first test opens a
// transaction. `install_tables()` rather than `create_tables()` so the DDL runs
// without the online-event term insert, which would commit permanently, and
// without the taxonomy re-registration that carries.
PMC\Unit_Test\Utility::invoke_hidden_method( GatherPress\Core\Setup::get_instance(), 'install_tables' );

// Clear anything a previous run leaked past its rollback, so a dirty database
// starts the suite from the state a fresh one does.
gatherpress_reset_custom_tables();

/**
 * Empty GatherPress's custom tables without issuing DDL.
 *
 * `WP_UnitTestCase` wraps every test in a transaction and rolls it back.
 * DDL implicitly commits in MySQL and MariaDB, so a `CREATE TABLE` (or a
 * `TRUNCATE`) issued from a test's `setUp()` ends that transaction and every
 * row the test writes afterwards survives the rollback and leaks into the rest
 * of the run. `DELETE FROM` is DML and rolls back cleanly, so it is what test
 * classes use to start from an empty occurrence table. The tables themselves
 * are created once in the bootstrap, before the first test transaction opens.
 *
 * Only the rows are cleared. `create_tables()`'s other side effects are
 * deliberately not reproduced, neither adding the online-event term nor
 * scheduling a rewrite flush: re-registering the venue taxonomy outside a
 * test's own lifecycle clobbers the object-type list the calendar suite reads,
 * and the term insert would commit permanently from the bootstrap.
 *
 * @since 0.36.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return void
 */
function gatherpress_reset_custom_tables(): void {
	global $wpdb;

	$tables = array(
		sprintf( GatherPress\Core\Event::TABLE_FORMAT, $wpdb->prefix ),
		sprintf( GatherPress\Core\Event\Recurrence\Occurrences::TABLE_FORMAT, $wpdb->prefix ),
	);

	foreach ( $tables as $gatherpress_table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- test harness reset of a plugin-owned table.
		$wpdb->query( "DELETE FROM `{$gatherpress_table}`" );
	}
}
