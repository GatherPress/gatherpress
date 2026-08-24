<?php
/**
 * The "Add_To_Calendar" class handles the functionality of the Add to Calendar block,
 * ensuring proper rendering and behavior for calendar integration.
 *
 * This class is responsible for transforming block content to replace calendar
 * placeholder hrefs with fully-generated calendar URLs based on event metadata.
 * It enables users to add events to services like Google, iCal, Outlook, and Yahoo.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Add_To_Calendar" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 0.33.0
 */
final class Add_To_Calendar {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 0.33.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/add-to-calendar';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.33.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.33.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		add_filter( $render_block_hook, array( $this, 'replace_calendar_placeholders' ), 10, 2 );
		// Priority 11 so the notice is injected after replace_calendar_placeholders
		// (priority 10) has built the final per-service hrefs onto the anchors,
		// and after any third-party render filter at the default priority.
		add_filter( $render_block_hook, array( $this, 'inject_new_tab_notices' ), 11 );
	}

	/**
	 * Replace placeholder calendar hrefs with generated event URLs.
	 *
	 * Scans the block content for known calendar link placeholders (e.g.,
	 * #gatherpress-google-calendar) and replaces them with fully-formed
	 * URLs based on the associated event data. This ensures that "Add to Calendar"
	 * links point to the correct service with event details.
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The original block content.
	 * @param array<string, mixed> $block         The block instance array, used to determine the event.
	 *
	 * @return string The modified block content with calendar hrefs replaced.
	 */
	public function replace_calendar_placeholders( string $block_content, array $block ): string {
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Validate that the post type supports event_date.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ||
			! Event::is_viewable( $post_id )
		) {
			return '';
		}

		$event          = new Event( $post_id );
		$tag            = new WP_HTML_Tag_Processor( $block_content );
		$calendar_links = $event->get_calendar_links();
		// iCal and Outlook entries surface as `download` URLs (the new
		// `/event/{slug}/ical|outlook` endpoints serve attachments with
		// `Content-Disposition: attachment`), while Google and Yahoo are
		// off-site redirects keyed under `link`. Fall back across both so
		// older themes that haven't migrated still get a valid href.
		$ical_href    = $calendar_links['ical']['download'] ?? $calendar_links['ical']['link'] ?? '';
		$outlook_href = $calendar_links['outlook']['download'] ?? $calendar_links['outlook']['link'] ?? '';
		$replacements = array(
			'#gatherpress-google-calendar'  => $calendar_links['google']['link'] ?? '',
			'#gatherpress-ical-calendar'    => $ical_href,
			'#gatherpress-outlook-calendar' => $outlook_href,
			'#gatherpress-yahoo-calendar'   => $calendar_links['yahoo']['link'] ?? '',
		);

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$href = $tag->get_attribute( 'href' );

			if ( isset( $replacements[ $href ] ) && $replacements[ $href ] ) {
				$tag->set_attribute( 'href', $replacements[ $href ] );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Inject a screen-reader new-tab notice into each targeted _blank link.
	 *
	 * Walks the block content and appends a visually hidden "( opens in a new
	 * tab )" warning to every anchor that opens in a new tab, so screen-reader
	 * users get the same cue sighted users see. Anchors that already carry the
	 * marker class are left untouched, keeping the transform idempotent when
	 * the filter runs more than once against the same content.
	 *
	 * Uses WP_HTML_Tag_Processor rather than a regex so the tag parser owns
	 * every decision: a target only counts when it is the actual target
	 * attribute (not text inside another attribute), unquoted target=_blank is
	 * matched, and the existing-notice check runs through has_class so any
	 * quoting or class order on this or the injected span is honored.
	 *
	 * The processor cannot insert markup (attributes and plain text only), so
	 * the parser records its decisions as temporary data-* markers with known,
	 * uniform quoting and a second pass does the single splice at each marked
	 * anchor's closing tag.
	 *
	 * @since 0.36.0
	 *
	 * @param string $block_content The block content to parse.
	 *
	 * @return string The block content with new-tab notices injected.
	 */
	public function inject_new_tab_notices( string $block_content ): string {
		$processor = new WP_HTML_Tag_Processor( $block_content );
		$inside    = false;

		// First pass: the parser makes every decision, recorded as temporary
		// markers written with known, uniform quoting.
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			if ( 'A' === $processor->get_tag() ) {
				if ( $processor->is_tag_closer() ) {
					$inside = false;
				} elseif ( '_blank' === $processor->get_attribute( 'target' ) ) {
					$inside = true;
					$processor->set_attribute( 'data-gatherpress-new-tab', '1' );
				}

				continue;
			}

			if (
				$inside
				&& 'SPAN' === $processor->get_tag()
				&& ! $processor->is_tag_closer()
				&& $processor->has_class( 'gatherpress-new-tab-notice' )
			) {
				$processor->set_attribute( 'data-gatherpress-has-notice', '1' );
			}
		}

		$html = $processor->get_updated_html();

		// Second pass: pure splicing on the markers the parser wrote. Anchors
		// cannot nest, so the next </a> after a marked opener closes it.
		$marker = ' data-gatherpress-new-tab="1"';
		$notice = sprintf(
			'<span class="screen-reader-text gatherpress-new-tab-notice"> %1$s</span>',
			esc_html__( '(opens in a new tab)', 'gatherpress' )
		);

		$offset   = 0;
		$position = strpos( $html, $marker, $offset );

		while ( false !== $position ) {
			$close = stripos( $html, '</a>', $position );

			if ( false !== $close ) {
				$range = substr( $html, $position, $close - $position );

				if ( ! str_contains( $range, 'data-gatherpress-has-notice' ) ) {
					$html = substr_replace( $html, $notice, $close, 0 );
				}
			}

			$offset   = $position + 1;
			$position = strpos( $html, $marker, $offset );
		}

		return str_replace(
			array( $marker, ' data-gatherpress-has-notice="1"' ),
			'',
			$html
		);
	}
}
