<?php
/**
 * Background warmer for the venue-map static PNG cache.
 *
 * Scans FSE templates, template parts, and event post content for
 * `gatherpress/venue-map` blocks, collects every distinct (zoom, width,
 * height, aspectRatio) combo they request, and enqueues per-venue cron
 * jobs that drive {@see Map::warm()}. The goal is to keep the first
 * render of a venue page instant — once the venue has been saved, every
 * combo the site's templates reference has a PNG on disk before any
 * front-end request arrives.
 *
 * Why not rely on render-time fallback alone: when a `venue-map` block
 * sits inside a Single Venue FSE template, no editor pass ever knows
 * which venue it will ultimately render for, so there's no natural moment
 * when a user could click "Regenerate Map". Prewarming bridges that gap
 * by enumerating template combos × venues and generating eagerly.
 *
 * Scheduling is WP-Cron for now. Action Scheduler integration will
 * replace this layer once it's pulled into the plugin.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 1.0.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Post;

/**
 * Class Prewarm.
 *
 * Scans templates + events for venue-map combos, enqueues cron jobs to
 * warm each (venue, combo) via {@see Map::warm()}.
 *
 * @since 1.0.0
 */
class Prewarm {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cron action fired for each (venue, combo) warm job.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CRON_ACTION = 'gatherpress_static_map_prewarm_run';

	/**
	 * One-shot cron action that re-runs the full template scan + venue
	 * enumeration, used when the active provider changes mid-flight. A
	 * single deferred tick is cheaper than fanning out the per-(venue,
	 * combo) cron events synchronously inside the admin save that
	 * triggered the platform switch.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FULL_SWEEP_ACTION = 'gatherpress_static_map_prewarm_sweep';

	/**
	 * Block name this class watches for.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/venue-map';

	/**
	 * Batch size for paginated post scans — small enough that a single
	 * batch fits comfortably in memory even when post_content is large,
	 * big enough that pagination overhead stays cheap.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const SCAN_BATCH_SIZE = 500;

	/**
	 * Batch size for scans that load full post objects (post_content
	 * included). FSE-rich pages can make post_content multi-megabyte, so
	 * we pull fewer rows per round-trip than the ID-only venue scan
	 * (SCAN_BATCH_SIZE). Filterable via
	 * `gatherpress_static_map_prewarm_content_batch_size`.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CONTENT_SCAN_BATCH_SIZE = 100;

	/**
	 * Class constructor — wires hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Return the batch size used by paginated scans.
	 *
	 * Wraps the `SCAN_BATCH_SIZE` constant in a filter so tests (and power
	 * users on unusual installs) can shrink the batch without touching the
	 * class. Clamped to at least 1 to avoid an infinite-empty-batch loop.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_scan_batch_size(): int {
		/**
		 * Filter the venue-map prewarm scan batch size.
		 *
		 * @since 1.0.0
		 *
		 * @param int $size Number of posts loaded per batch during prewarm scans.
		 */
		$size = (int) apply_filters( 'gatherpress_static_map_prewarm_batch_size', self::SCAN_BATCH_SIZE );

		return max( 1, $size );
	}

	/**
	 * Return the batch size used by paginated content scans.
	 *
	 * Separate from {@see self::get_scan_batch_size()} because content
	 * scans load full post rows (post_content can be megabytes on
	 * FSE-rich pages), where the ID-only venue scan can afford a larger
	 * batch. Clamped to [1, 1000] — the floor avoids an infinite empty-
	 * batch loop, and the ceiling caps a misbehaving filter that would
	 * otherwise try to load every event into memory in a single query.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_content_scan_batch_size(): int {
		/**
		 * Filter the venue-map prewarm content-scan batch size.
		 *
		 * @since 1.0.0
		 *
		 * @param int $size Number of posts loaded per batch during content scans.
		 */
		$size = (int) apply_filters(
			'gatherpress_static_map_prewarm_content_batch_size',
			self::CONTENT_SCAN_BATCH_SIZE
		);

		return min( 1000, max( 1, $size ) );
	}

	/**
	 * Register save hooks, theme-switch hook, and the cron action handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( self::CRON_ACTION, array( $this, 'process_warm_job' ), 10, 5 );
		add_action( self::FULL_SWEEP_ACTION, array( $this, 'on_theme_switched' ) );

		// Priority 12 — after Map::maybe_generate() (priority 11) has
		// synchronously rendered whatever combos were already cached on the
		// venue. This hook picks up template combos the venue has never
		// been rendered at.
		add_action( 'wp_after_insert_post', array( $this, 'on_post_saved' ), 12, 2 );
		add_action( 'switch_theme', array( $this, 'on_theme_switched' ) );
	}

	/**
	 * Schedule the full template + venue rescan to run on the next cron
	 * tick. Used by callers that need a re-sweep but shouldn't pay the
	 * cost inline — `Map::maybe_handle_settings_change()` after a
	 * `map_platform` switch is the primary caller.
	 *
	 * Idempotent: if a sweep is already scheduled, no second event is
	 * queued, so a flurry of platform-saves coalesces into one tick.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_full_sweep(): void {
		if ( false !== wp_next_scheduled( self::FULL_SWEEP_ACTION ) ) {
			return;
		}

		wp_schedule_single_event( time() + 1, self::FULL_SWEEP_ACTION );
	}

	/**
	 * Dispatch a post save to the right enqueue path based on its type.
	 *
	 * Venues, FSE templates, and template parts each pull a different slice
	 * of the (venue, combo) grid; events and any other `gatherpress-venue`-
	 * carrying post type also contribute combos via their own embedded
	 * venue-map blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID that just saved.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function on_post_saved( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Only published posts contribute to the cached render set — a
		// draft/auto-draft venue hasn't been seen by any front-end request,
		// a trashed one shouldn't get new warm jobs scheduled against it,
		// and an unpublished template won't render. The scan paths reading
		// venue / event content already filter `post_status => publish`, so
		// this gate keeps the save-hook path consistent.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			$this->enqueue_for_all_venues( $this->collect_combos_from_content( $post->post_content ) );
			return;
		}

		if ( post_type_supports( $post->post_type, 'gatherpress-venue-information' ) ) {
			$this->enqueue_for_venue( $post_id );
			return;
		}

		// Event post types (or any post type that carries venue-map inside
		// a gatherpress/venue parent). Collect combos from the post's own
		// content, but enqueue those combos against the associated venue,
		// not against the event post itself.
		if ( post_type_supports( $post->post_type, 'gatherpress-venue' ) ) {
			$combos = $this->collect_combos_from_content( $post->post_content );
			if ( empty( $combos ) ) {
				return;
			}

			$venue = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $post_id );

			if ( $venue instanceof WP_Post ) {
				foreach ( $combos as $combo ) {
					$this->enqueue_warm_job( $venue->ID, $combo );
				}
			}
		}
	}

	/**
	 * Full rescan after a theme switch — new theme ships new templates, so
	 * combos for every venue may have changed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function on_theme_switched(): void {
		$this->enqueue_for_all_venues( $this->collect_all_template_combos() );
	}

	/**
	 * Cron handler. Delegates to {@see Map::warm()}.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id      Venue post ID.
	 * @param int    $zoom         Zoom level.
	 * @param int    $width        Pixel width (0 = auto).
	 * @param int    $height       Pixel height (0 = auto).
	 * @param string $aspect_ratio Aspect-ratio string.
	 * @return void
	 */
	public function process_warm_job( int $post_id, int $zoom, int $width, int $height, string $aspect_ratio ): void {
		Map::get_instance()->warm( $post_id, $zoom, $width, $height, $aspect_ratio );
	}

	/**
	 * Enqueue every known template combo for a single venue.
	 *
	 * @since 1.0.0
	 *
	 * @param int $venue_post_id Venue post ID.
	 * @return void
	 */
	protected function enqueue_for_venue( int $venue_post_id ): void {
		foreach ( $this->collect_all_template_combos() as $combo ) {
			$this->enqueue_warm_job( $venue_post_id, $combo );
		}
	}

	/**
	 * Enqueue a list of combos against every supported venue.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{zoom:int,width:int,height:int,aspect_ratio:string}> $combos Combo list.
	 * @return void
	 */
	protected function enqueue_for_all_venues( array $combos ): void {
		if ( empty( $combos ) ) {
			return;
		}

		$types = get_post_types_by_support( 'gatherpress-venue-information' );
		if ( empty( $types ) ) {
			return;
		}

		// Stream venues in batches so a site with thousands of venues never
		// loads the full ID set into memory on the save hook.
		$batch_size = $this->get_scan_batch_size();
		$page       = 1;
		while ( true ) {
			$batch = get_posts(
				array(
					'post_type'              => $types,
					'post_status'            => 'publish',
					'posts_per_page'         => $batch_size,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					// Only IDs are used — skip meta and term cache priming.
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $venue_post_id ) {
				foreach ( $combos as $combo ) {
					$this->enqueue_warm_job( (int) $venue_post_id, $combo );
				}
			}

			if ( count( $batch ) < $batch_size ) {
				break;
			}

			++$page;
		}
	}

	/**
	 * Schedule a single warm job, deduped via wp_next_scheduled().
	 *
	 * The actual scheduling is wrapped in the
	 * {@see 'gatherpress_static_map_prewarm_pre_enqueue_job'} short-circuit filter so
	 * a companion plugin (e.g. "GatherPress at Scale") can intercept and
	 * route the fanout through Action Scheduler — or any other queue —
	 * without touching core. Returning a non-null value from the filter
	 * suppresses the default WP-Cron path.
	 *
	 * @since 1.0.0
	 *
	 * @param int                                                      $venue_post_id Venue post ID.
	 * @param array{zoom:int,width:int,height:int,aspect_ratio:string} $combo         Combo to warm.
	 * @return void
	 */
	protected function enqueue_warm_job( int $venue_post_id, array $combo ): void {
		$args = array(
			$venue_post_id,
			(int) $combo['zoom'],
			(int) $combo['width'],
			(int) $combo['height'],
			(string) $combo['aspect_ratio'],
		);

		/**
		 * Filter the prewarm enqueue call to take over scheduling.
		 *
		 * Return any non-null value from this filter to suppress both
		 * the WP-Cron dedup check below and the `wp_schedule_single_event()`
		 * call — a companion plugin that hooks this filter owns the
		 * full scheduling path end-to-end (including its own dedup,
		 * since the fanout by-passes `wp_next_scheduled()`). Mirrors
		 * the core `pre_*` filter convention: `null` means "pass
		 * through to the default"; everything else, including falsy
		 * values like `false`, `0`, and `''`, short-circuits.
		 *
		 * Core ignores the return value past the null check, so a
		 * callback is free to return whatever is useful to itself —
		 * the established convention is a scheduler-specific
		 * identifier (e.g. the Action Scheduler action ID returned by
		 * `as_enqueue_async_action()`) so other filters / debug
		 * tooling downstream can correlate the job.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed  $short_circuit Non-null to suppress the default enqueue.
		 * @param string $hook          Action hook name fired when the job runs.
		 * @param array  $args          Args passed to the action hook when the job runs:
		 *                              `array( $venue_post_id, $zoom, $width, $height, $aspect_ratio )`.
		 */
		$short_circuit = apply_filters(
			'gatherpress_static_map_prewarm_pre_enqueue_job',
			null,
			self::CRON_ACTION,
			$args
		);

		if ( null !== $short_circuit ) {
			return;
		}

		if ( false !== wp_next_scheduled( self::CRON_ACTION, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + 1, self::CRON_ACTION, $args );
	}

	/**
	 * Aggregate combos from every source the site renders venue-map blocks in:
	 * DB templates, DB template parts, theme file templates, theme file
	 * template parts, and the content of every post whose type supports
	 * `gatherpress-venue`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{zoom:int,width:int,height:int,aspect_ratio:string}>
	 */
	protected function collect_all_template_combos(): array {
		$combos = array();

		if ( function_exists( 'get_block_templates' ) ) {
			foreach ( get_block_templates( array(), 'wp_template' ) as $template ) {
				$combos = array_merge(
					$combos,
					$this->collect_combos_from_content( (string) $template->content )
				);
			}
			foreach ( get_block_templates( array(), 'wp_template_part' ) as $template_part ) {
				$combos = array_merge(
					$combos,
					$this->collect_combos_from_content( (string) $template_part->content )
				);
			}
		}

		$venue_carrying_types = get_post_types_by_support( 'gatherpress-venue' );
		if ( ! empty( $venue_carrying_types ) ) {
			// Full post rows (for post_content) — use the smaller
			// content-scan batch. FSE-rich events can carry MBs of
			// content; the ID-only venue scan can afford a larger batch.
			$batch_size = $this->get_content_scan_batch_size();
			$page       = 1;

			// Paginate rather than `posts_per_page => -1` — a site with
			// thousands of events would otherwise load every post into
			// memory at once on the hook that triggered us (venue save,
			// template save, theme switch).
			while ( true ) {
				$batch = get_posts(
					array(
						'post_type'              => $venue_carrying_types,
						'post_status'            => 'publish',
						'posts_per_page'         => $batch_size,
						'paged'                  => $page,
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'no_found_rows'          => true,
						// Only post_content is read — skip meta/term priming.
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);

				if ( empty( $batch ) ) {
					break;
				}

				foreach ( $batch as $post ) {
					$combos = array_merge(
						$combos,
						$this->collect_combos_from_content( $post->post_content )
					);
				}

				if ( count( $batch ) < $batch_size ) {
					break;
				}

				++$page;
			}
		}

		return $this->dedupe_combos( $combos );
	}

	/**
	 * Parse block markup and return every venue-map combo it contains.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Block markup (post_content / template content).
	 * @return array<int, array{zoom:int,width:int,height:int,aspect_ratio:string}>
	 */
	protected function collect_combos_from_content( string $content ): array {
		if ( '' === $content || false === strpos( $content, self::BLOCK_NAME ) ) {
			return array();
		}

		$blocks = parse_blocks( $content );

		return $this->walk_blocks_for_combos( $blocks );
	}

	/**
	 * Recurse through a parsed block tree and collect venue-map combos.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array{zoom:int,width:int,height:int,aspect_ratio:string}>
	 */
	protected function walk_blocks_for_combos( array $blocks ): array {
		$combos = array();

		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? null ) === self::BLOCK_NAME ) {
				$combos[] = $this->extract_block_combo( (array) ( $block['attrs'] ?? array() ) );
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$combos = array_merge(
					$combos,
					$this->walk_blocks_for_combos( $block['innerBlocks'] )
				);
			}
		}

		return $combos;
	}

	/**
	 * Pull a combo tuple out of a block's attributes, filling in site
	 * defaults for anything the block didn't explicitly set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array{zoom:int,width:int,height:int,aspect_ratio:string}
	 */
	protected function extract_block_combo( array $attrs ): array {
		return array(
			'zoom'         => isset( $attrs['zoom'] ) ? (int) $attrs['zoom'] : Map::DEFAULT_ZOOM,
			'width'        => isset( $attrs['width'] ) ? (int) $attrs['width'] : 0,
			'height'       => isset( $attrs['height'] )
				? (int) $attrs['height']
				: Map::DEFAULT_HEIGHT,
			'aspect_ratio' => isset( $attrs['aspectRatio'] )
				? (string) $attrs['aspectRatio']
				: Map::DEFAULT_ASPECT_RATIO,
		);
	}

	/**
	 * Dedupe a combo list keyed by (zoom, width, height, aspect_ratio).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{zoom:int,width:int,height:int,aspect_ratio:string}> $combos Combo list.
	 * @return array<int, array{zoom:int,width:int,height:int,aspect_ratio:string}>
	 */
	protected function dedupe_combos( array $combos ): array {
		$seen   = array();
		$unique = array();

		foreach ( $combos as $combo ) {
			$key = sprintf(
				'%d|%d|%d|%s',
				(int) $combo['zoom'],
				(int) $combo['width'],
				(int) $combo['height'],
				(string) $combo['aspect_ratio']
			);
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$unique[]     = $combo;
		}

		return $unique;
	}

	/**
	 * Every post ID whose post type is a venue source.
	 *
	 * Intended for small-scale contexts (tests, on-demand scans where the
	 * caller knows the venue count is bounded). Production fan-out paths
	 * use `enqueue_for_all_venues()` instead, which streams through the
	 * venue set in batches without ever holding the full list in memory.
	 *
	 * @since 1.0.0
	 *
	 * @return int[]
	 */
	protected function get_venue_post_ids(): array {
		$types = get_post_types_by_support( 'gatherpress-venue-information' );
		if ( empty( $types ) ) {
			return array();
		}

		$ids        = array();
		$batch_size = $this->get_scan_batch_size();
		$page       = 1;

		while ( true ) {
			$batch = get_posts(
				array(
					'post_type'              => $types,
					'post_status'            => 'publish',
					'posts_per_page'         => $batch_size,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					// Only IDs are used — skip meta and term cache priming.
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $id ) {
				$ids[] = (int) $id;
			}

			if ( count( $batch ) < $batch_size ) {
				break;
			}

			++$page;
		}

		return $ids;
	}
}
