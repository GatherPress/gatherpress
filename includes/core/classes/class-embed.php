<?php
/**
 * Adds event and venue details to post embeds.
 *
 * WordPress renders every embedded post with the same generic template:
 * title, excerpt and site name. This class adds the details that make an
 * embedded event or venue recognizable, keyed off post type supports so
 * any post type acting as an event or venue gets the same treatment.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Venue;
use WP_Post;

/**
 * Class Embed.
 *
 * Renders event and venue details inside WordPress post embeds.
 *
 * @since TBD
 */
final class Embed {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for the embed template.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'embed_content', array( $this, 'render_details' ) );
		add_action( 'enqueue_embed_scripts', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Prints the details of the embedded post.
	 *
	 * @since TBD
	 *
	 * @return void
	 *
	 * @throws Exception If date/time formatting fails.
	 */
	public function render_details(): void {
		// The templates escape every value.
		echo $this->get_details_markup( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Enqueues the embed stylesheet when the embedded post is an event or venue.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function enqueue_style(): void {
		$post = get_post();

		if (
			! $post instanceof WP_Post
			|| (
				! post_type_supports( $post->post_type, Event::SUPPORT )
				&& ! post_type_supports( $post->post_type, Venue::SUPPORT )
			)
		) {
			return;
		}

		$asset = Assets::get_instance()->get_asset_data( 'embed_style' );

		wp_enqueue_style(
			'gatherpress-embed-style',
			GATHERPRESS_CORE_URL . 'build/embed_style.css',
			$asset['dependencies'],
			$asset['version']
		);
	}

	/**
	 * Builds the details markup for an embedded event or venue.
	 *
	 * A password-protected post shows nothing, matching how WordPress hides
	 * its excerpt in the embed.
	 *
	 * @since TBD
	 *
	 * @param WP_Post|null $post The embedded post.
	 *
	 * @return string The details markup, or an empty string for any other post.
	 *
	 * @throws Exception If date/time formatting fails.
	 */
	public function get_details_markup( ?WP_Post $post ): string {
		if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return '';
		}

		$markup = '';

		if ( post_type_supports( $post->post_type, Event::SUPPORT ) ) {
			$event = new Event( $post->ID );

			// An embed iframe can be cached and shown to anyone, so it only gets the
			// venue details calendars and feeds may share.
			$venue = $event->get_public_venue_information();
			$state = 'upcoming';

			if ( ( new Status( $post->ID ) )->is_canceled() ) {
				$state = 'canceled';
			} elseif ( $event->has_event_past() ) {
				$state = 'past';
			}

			$markup = Utility::render_template(
				sprintf( '%s/includes/templates/embed/event.php', GATHERPRESS_CORE_PATH ),
				array(
					'datetime'   => $event->get_display_datetime(),
					// The permalink is only set when a real venue post is attached,
					// so the online-event term name never shows as a venue.
					'venue_name' => '' !== $venue['permalink'] ? $venue['name'] : '',
					'address'    => '' !== $venue['permalink'] ? $venue['address'] : '',
					'is_online'  => $event->is_online(),
					'state'      => $state,
					'rsvp_link'  => 'upcoming' === $state && post_type_supports( $post->post_type, Rsvp::SUPPORT )
						? (string) get_permalink( $post )
						: '',
				)
			);
		} elseif ( post_type_supports( $post->post_type, Venue::SUPPORT ) ) {
			$markup = Utility::render_template(
				sprintf( '%s/includes/templates/embed/venue.php', GATHERPRESS_CORE_PATH ),
				array(
					'information' => ( new Venue( $post->ID ) )->get_information(),
				)
			);
		}

		return $markup;
	}
}
