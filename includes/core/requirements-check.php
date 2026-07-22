<?php
/**
 * GatherPress Plugin Requirements Check.
 *
 * This file checks the system requirements before loading the GatherPress plugin.
 *
 * Runs before the autoloader is registered, so the notice classes it uses are
 * required explicitly rather than autoloaded. Everything under
 * `classes/admin/notices/` is written to parse on very old PHP for this reason
 * -- see `class-base.php` for the constraint and why it matters.
 *
 * Each notice owns its own condition via `applies()`, so this file asks the
 * notice whether it applies rather than duplicating the check. The condition
 * that halts loading and the condition that shows the notice cannot drift
 * apart, because they are the same method.
 *
 * These notices are non-persistent: dismissal is recorded by
 * GatherPress\Core\Admin\Notifications, which only loads once requirements
 * pass, and a blocking failure is not something to let an administrator
 * silence anyway.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

use GatherPress\Core\Admin\Notices\Missing_Build;
use GatherPress\Core\Admin\Notices\Requires_Php;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/admin/notices/class-base.php';
require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/admin/notices/class-requires-php.php';
require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/admin/notices/class-missing-build.php';

$gatherpress_activation = true;

/**
 * Blocking notices, in the order they are evaluated.
 *
 * Any that applies both shows its notice and prevents GatherPress loading.
 *
 * @var GatherPress\Core\Admin\Notices\Base[] $gatherpress_blocking_notices
 */
$gatherpress_blocking_notices = array(
	new Requires_Php(),
	new Missing_Build(),
);

foreach ( $gatherpress_blocking_notices as $gatherpress_blocking_notice ) {
	if ( ! $gatherpress_blocking_notice->applies() ) {
		continue;
	}

	add_action( 'admin_notices', array( $gatherpress_blocking_notice, 'render' ) );

	$gatherpress_activation = false;
}

return $gatherpress_activation;
