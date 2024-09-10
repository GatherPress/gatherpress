<?php
/**
 * Class responsible for managing calendar-related endpoints in GatherPress.
 *
 * This file defines the `Calendar_Endpoints` class, which is responsible for
 * registering and managing custom endpoints related to calendar functionality,
 * such as export to third-party calendars and iCal download.
 *
 * It utilizes the `Posttype_Single_Endpoint` class to create endpoints
 * for single calendar events and provides logic for template rendering and external redirects.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Endpoints\Posttype_Single_Endpoint;
use GatherPress\Core\Endpoints\Posttype_Single_Feed_Endpoint;
use GatherPress\Core\Endpoints\Posttype_Feed_Endpoint;
use GatherPress\Core\Endpoints\Taxonomy_Feed_Endpoint;
use GatherPress\Core\Endpoints\Endpoint_Redirect;
use GatherPress\Core\Endpoints\Endpoint_Template;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use WP_Term;

/**
 * Manages Custom Calendar Endpoints for GatherPress.
 *
 * The `Calendar_Endpoints` class handles the registration and management of
 * custom endpoints for calendar-related functionality in GatherPress, such as:
 * - Adding Google Calendar and Yahoo Calendar links for events.
 * - Providing iCal and Outlook download templates for events.
 *
 * The class uses the `Posttype_Single_Endpoint` to create these endpoints for
 * single instances of the `gatherpress_event` post type. It also handles the
 * redirection logic for external calendars and provides the necessary templates
 * for downloading calendar data.
 *
 * @since 1.0.0
 */
class Calendar_Endpoints {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	const QUERY_VAR = 'gatherpress_calendar';
	const ICAL_SLUG = 'ical'; // Make sure nobody tries to change or translate this string ;) !

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for registering custom calendar endpoints.
	 *
	 * This method hooks into the `registered_post_type_{post_type}` action to ensure that
	 * the custom endpoints for the `gatherpress_event` post type are registered after the
	 * post type is initialized.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action(
			sprintf(
				'registered_post_type_%s',
				'gatherpress_event'
			),
			array( $this, 'init_events' ),
		);
		add_action(
			sprintf(
				'registered_post_type_%s',
				'gatherpress_venue'
			),
			array( $this, 'init_venues' ),
		);
		// @todo Maybe hook this two actions dynamically based on a registered post type?!
		add_action(
			'registered_taxonomy_for_object_type',
			array( $this, 'init_taxonomies' ),
			10,
			2
		);
		add_action(
			'registered_taxonomy',
			array( $this, 'init_taxonomies' ),
			10,
			2
		);
		add_action( 'wp_head', array( $this, 'alternate_links' ) );
	}

	/**
	 * Initializes the custom calendar endpoints for single events.
	 *
	 * This method sets up a `Posttype_Single_Endpoint` for the `gatherpress_event` post type
	 * (because this is this class' default post type),
	 * adding custom endpoints for external calendar services (Google Calendar, Yahoo Calendar)
	 * and download templates for iCal and Outlook.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_events(): void {

		// Important: Register the feed endpoint before the single endpoint,
		// to make sure rewrite rules get saved in the correct order.
		new Posttype_Feed_Endpoint(
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
		);
		new Posttype_Single_Endpoint(
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_download_template' ) ),
				new Endpoint_Template( 'outlook', array( $this, 'get_ical_download_template' ) ),
				new Endpoint_Redirect( 'google-calendar', array( $this, 'get_redirect_to' ) ),
				new Endpoint_Redirect( 'yahoo-calendar', array( $this, 'get_redirect_to' ) ),
			),
			self::QUERY_VAR
		);
	}

	/**
	 * Initializes the custom calendar endpoints for single venues.
	 *
	 * This method sets up a `Posttype_Single_Endpoint` for the `gatherpress_venue` post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_venues(): void {
		new Posttype_Single_Feed_Endpoint(
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
		);
	}

	/**
	 * Initializes the custom calendar endpoints for taxonomies that belong to events.
	 *
	 * This method sets up one `Taxonomy_Feed_Endpoint` for each taxonomy,
	 * that is registered for the `gatherpress_event` post type
	 * and publicly available.
	 *
	 * @param  string       $taxonomy    Name of the taxonomy that got registered last.
	 * @param  string|array $object_type This will be a string when called via 'registered_taxonomy_for_object_type',
	 *                                   and could(!) be an array when called from 'registered_taxonomy'.
	 *
	 * @return void
	 */
	public function init_taxonomies( string $taxonomy, string|array $object_type ): void {

		// Stop, if the currently registered taxonomy is ...
		if ( // ... not registered for the events post type.
			! in_array( 'gatherpress_event', (array) $object_type, true ) ||
			// ... GatherPress' shadow-taxonomy for venues.
			'_gatherpress_venue' === $taxonomy ||
			// ... should not be public.
			! is_taxonomy_viewable( $taxonomy )
		) {
			return;
		}

		new Taxonomy_Feed_Endpoint(
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$taxonomy
		);
	}

	public function alternate_links(): void {

		if ( ! current_theme_supports( 'automatic-feed-links' ) ) {
			return;
		}

		// @todo "add_filter('feed_content_type')" here, if the subscribe-able feed need something different than text/cal.

		$args = array(
			'blogtitle'     => get_bloginfo( 'name' ),
			/* translators: Separator between site name and feed type in feed links. */
			'separator'     => _x( '&raquo;', 'feed link', 'default' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Post title. */
			'singletitle'   => __( '📅 %1$s %2$s %3$s iCal Download', 'gatherpress' ),
			/* translators: 1: Site title, 2: Separator (raquo). */
			'feedtitle'     => __( '📅 %1$s %2$s iCal Feed', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Post type name. */
			'posttypetitle' => __( '📅 %1$s %2$s %3$s iCal Feed', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Term name, 4: Taxonomy singular name. */
			'taxtitle'      => __( '📅 %1$s %2$s %3$s %4$s iCal Feed', 'gatherpress' ),
		);

		$alternate_links = array();

		// @todo "/feed/ical" could be enabled as alias of "/event/feed/ical",
		// and called with "get_feed_link( self::ICAL_SLUG )".
		$alternate_links[] = array(
			'url'  => get_post_type_archive_feed_link( 'gatherpress_event', self::ICAL_SLUG ),
			'attr' => sprintf(
				$args['feedtitle'],
				$args['blogtitle'],
				$args['separator']
			),
		);

		if ( is_singular( 'gatherpress_event' ) ) {
			$alternate_links[] = array(
				'url'  => trailingslashit( get_permalink() ) . self::ICAL_SLUG,
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);

			// Get all terms, associated with the current event-post.
			$terms = get_terms(
				array(
					'taxonomy'   => get_object_taxonomies( get_queried_object_id() ),
					'object_ids' => get_queried_object_id(),
				)
			);
			// Loop over terms and generate the ical feed links for the <head>.
			array_walk(
				$terms,
				function ( WP_Term $term ) use ( $args, &$alternate_links ) {
					$tax = get_taxonomy( $term->taxonomy );
					switch ( $term->taxonomy ) {
						case '_gatherpress_venue':
							$gatherpress_venue = Venue::get_instance()->get_venue_post_from_term_slug( $term->slug );

							// An Online-Event will have no Venue; prevent error on non-existent object.
							// Feels weird to use a *_comments_* function here, but it delivers clean results
							// in the form of "domain.tld/event/my-sample-event/feed/ical/".
							$href = ( $gatherpress_venue ) ? get_post_comments_feed_link( $gatherpress_venue->ID, self::ICAL_SLUG ) : null;
							break;

						default:
							$href = get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG );
							break;
					}
					// Can be empty for Online-Events.
					if ( ! empty( $href ) ) {
						$alternate_links[] = array(
							'url'  => $href,
							'attr' => sprintf(
								$args['taxtitle'],
								$args['blogtitle'],
								$args['separator'],
								$term->name,
								$tax->labels->singular_name
							),
						);
					}
				}
			);
		} elseif ( is_singular( 'gatherpress_venue' ) ) {

			// Feels weird to use a *_comments_* function here, but it delivers clean results
			// in the form of "domain.tld/venue/my-sample-venue/feed/ical/".
			$alternate_links[] = array(
				'url'  => get_post_comments_feed_link( 0, self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);
		} elseif ( is_tax() ) {
			$term = get_queried_object();

			if ( $term && is_object_in_taxonomy( 'gatherpress_event', $term->taxonomy ) ) {
				$tax = get_taxonomy( $term->taxonomy );

				$alternate_links[] = array(
					'url'  => get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG ),
					'attr' => sprintf(
						$args['taxtitle'],
						$args['blogtitle'],
						$args['separator'],
						$term->name,
						$tax->labels->singular_name
					),
				);
			}
		}

		// Render tags into <head/>.
		array_walk(
			$alternate_links,
			function ( $link ) {
				printf(
					'<link rel="alternate" type="%s" title="%s" href="%s" />' . "\n",
					esc_attr( isset( $link['type'] ) ? $link['type'] : 'text/calendar' ),
					esc_attr( $link['attr'] ),
					esc_url( $link['url'] )
				);
			}
		);
	}

	/**
	 * Returns the external calendar URL for the current event.
	 *
	 * This method generates the appropriate URL for either Google Calendar or Yahoo Calendar,
	 * depending on the value of the `gatherpress_ext_calendar` query variable. It uses the
	 * `Event` class to retrieve the necessary data for the event.
	 *
	 * @since 1.0.0
	 *
	 * @return string The URL to redirect the user to the appropriate calendar service.
	 */
	public function get_redirect_to(): string {
		$event = new Event( get_queried_object_id() );
		// Determine which calendar service to redirect to based on the query var.
		switch ( get_query_var( self::QUERY_VAR ) ) {
			case 'google-calendar':
				return $event->get_google_calendar_link();
			case 'yahoo-calendar':
				return $event->get_yahoo_calendar_link();
		}
	}

	/**
	 * Returns the template for the current calendar download.
	 *
	 * This method provides the template file to be used for iCal and Outlook downloads.
	 *
	 * By adding a file with the same name to your themes root folder
	 * or your themes `/templates` folder, this template will be used
	 * with priority over the default template provided by GatherPress.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing:
	 *               - 'file_name': the file name of the template to be loaded from the theme. Will load defaults from the plugin if theme files do not exist.
	 *               - 'dir_path':  (Optional) Absolute path to some template directory outside of the theme folder.
	 */
	public function get_ical_download_template(): array {
		return array(
			'file_name' => 'ical-download.php',
		);
	}

	/**
	 * Returns the template for the subscribeable calendar feed.
	 *
	 * This method provides the template file to be used for ical-feeds.
	 *
	 * By adding a file with the same name to your themes root folder
	 * or your themes `/templates` folder, this template will be used
	 * with priority over the default template provided by GatherPress.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing:
	 *               - 'file_name': the file name of the template to be loaded from the theme.
	 *                              Will load defaults from the plugin if theme files do not exist.
	 *               - 'dir_path':  (Optional) Absolute path to some template directory outside of the theme folder.
	 */
	public function get_ical_feed_template(): array {
		return array(
			'file_name' => 'ical-feed.php',
		);
	}
}
