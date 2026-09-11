<?php
/**
 * Scheduling for occurrence-horizon top-up.
 *
 * `Occurrences::project()` is idempotent and callable with no request
 * context, and `Occurrences::resolve_horizon()` measures from
 * `max( anchor, now )`, so re-running it genuinely extends an open-ended
 * series' projected horizon over time. This class owns *when* that re-run
 * happens: a recurring scheduled sweep and its deactivation cleanup. It is
 * kept separate from `Occurrences` so the repository class stays a storage and
 * projection API, and the scheduling concern (cron registration, WP-Cron vs.
 * Action Scheduler, deactivation) lives on its own, matching the existing
 * split between `Venue\Map` (renderer) and `Venue\Map\Prewarm` (scheduler).
 *
 * Scheduling follows the plugin's existing precedent (`Geocoding`,
 * `Venue\Map\Prewarm`): WP-Cron is the always-available default, and a
 * `null`-passthrough filter is the entire seam by which a companion plugin
 * can route the sweep through Action Scheduler instead. Neither existing
 * class calls an `as_*` function directly, so this class does not either.
 * There is no in-repo precedent for a `function_exists( 'as_enqueue_async_action' )`
 * check, only for the filter short-circuit.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Projection_Cron.
 *
 * Singleton owning the scheduled top-up sweep and its deactivation cleanup.
 *
 * @since 0.36.0
 */
final class Projection_Cron {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cron action fired for the scheduled top-up sweep.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SWEEP_ACTION = 'gatherpress_recurrence_top_up_sweep';

	/**
	 * WP-Cron recurrence for the sweep.
	 *
	 * Hourly is frequent enough that a series never drifts far past
	 * `Occurrences::TOP_UP_MARGIN_DAYS` of its horizon between sweeps, and
	 * infrequent enough that `run_sweep()`'s own `site_has_recurring_events()`
	 * short-circuit is the only cost most sites ever pay for it.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SWEEP_RECURRENCE = 'hourly';

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for the scheduled sweep and its deactivation cleanup.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'maybe_schedule_sweep' ) );
		add_action( self::SWEEP_ACTION, array( $this, 'run_sweep' ) );

		register_deactivation_hook( GATHERPRESS_CORE_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Schedule the recurring sweep, deduped via `wp_next_scheduled()`.
	 *
	 * Short-circuits on `Query::site_has_recurring_events()` first, so a site
	 * with no recurring events never schedules the cron event or writes the
	 * `wp_options` row that scheduling it performs. This runs on `init`, so it
	 * would otherwise cost every GatherPress install a permanent hourly cron
	 * event and an option write regardless of whether the site has ever
	 * published a recurring event at all.
	 *
	 * The flag is a transition, not a constant: a site that removes its last
	 * recurrence goes back to having no recurring events, and the hourly
	 * event scheduled while it did must be cleared rather than left
	 * dispatching an early-returning callback forever. Only deactivation
	 * cleaned it up before, which a site that keeps the plugin never reaches.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function maybe_schedule_sweep(): void {
		// The "a site with no recurring events pays nothing" guarantee
		// covers the scheduler itself, not only the sweep callback: an hourly
		// cron event and the wp_options write wp_schedule_event() performs
		// are both a cost, and the overwhelming majority of GatherPress
		// installs never publish a recurring event at all.
		if ( ! Query::site_has_recurring_events() ) {
			// Guarded on wp_next_scheduled() so the never-recurring site,
			// which is the overwhelming majority, still pays nothing: an
			// unguarded
			// wp_clear_scheduled_hook() rewrites the cron option on every
			// init of every install that has no sweep scheduled at all.
			if ( false !== wp_next_scheduled( self::SWEEP_ACTION ) ) {
				wp_clear_scheduled_hook( self::SWEEP_ACTION );
			}

			return;
		}

		/**
		 * Filters the sweep schedule call to take over scheduling.
		 *
		 * Return any non-null value from this filter to suppress the
		 * `wp_next_scheduled()` dedup check and the `wp_schedule_event()`
		 * call. A companion plugin that hooks this filter (e.g. one that
		 * routes the sweep through Action Scheduler) owns the full
		 * scheduling path end-to-end, including its own dedup. Mirrors
		 * `gatherpress_async_geocode_pre_enqueue_job`'s convention: `null`
		 * means "pass through to the default"; everything else short-circuits.
		 *
		 * @since 0.36.0
		 *
		 * @param mixed  $short_circuit Non-null to suppress the default WP-Cron scheduling.
		 * @param string $hook          Action hook name fired when the sweep runs.
		 *
		 * @return mixed Non-null to suppress the default WP-Cron scheduling.
		 */
		$short_circuit = apply_filters( 'gatherpress_recurrence_top_up_pre_schedule_sweep', null, self::SWEEP_ACTION );

		if ( null !== $short_circuit ) {
			return;
		}

		if ( false !== wp_next_scheduled( self::SWEEP_ACTION ) ) {
			return;
		}

		wp_schedule_event( time(), self::SWEEP_RECURRENCE, self::SWEEP_ACTION );
	}

	/**
	 * Run the scheduled sweep.
	 *
	 * Short-circuits on `Query::site_has_recurring_events()` before touching
	 * anything else, so a site with no recurring events issues no query at
	 * all against the occurrence table.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function run_sweep(): void {
		if ( ! Query::site_has_recurring_events() ) {
			return;
		}

		Occurrences::get_instance()->top_up();
	}

	/**
	 * Unschedule the sweep on plugin deactivation.
	 *
	 * Iterates every site on a network-wide deactivation, using the same
	 * bounded `switch_to_blog()` walk `Setup::activate_gatherpress_plugin()`
	 * uses, because each subsite stores the sweep in its own cron option and
	 * clearing only the current site orphans every child site's hourly event
	 * for as long as the plugin stays inactive. `wp_clear_scheduled_hook()`
	 * rather than `wp_unschedule_event()`, so every scheduled timestamp is
	 * removed, not only the earliest one `wp_next_scheduled()` reports.
	 *
	 * @since 0.36.0
	 *
	 * @param bool $network_wide Whether the plugin is being deactivated network-wide.
	 *
	 * @return void
	 */
	public function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields'     => 'ids',
					'network_id' => get_current_site()->id,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				wp_clear_scheduled_hook( self::SWEEP_ACTION );
				restore_current_blog();
			}
		} else {
			wp_clear_scheduled_hook( self::SWEEP_ACTION );
		}
	}
}
