<?php
/**
 * Notice welcoming someone who has just activated GatherPress.
 *
 * Unlike the requirement notices, this one renders after the requirements
 * gate, so it is ordinary modern PHP rather than the 7.4 subset described in
 * class-base.php.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.36.0
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use WP_Screen;

/**
 * Class Welcome.
 *
 * Activating a plugin drops you back on the plugins screen with nothing to go
 * on. This is the one moment GatherPress has someone's attention, so it says
 * what the plugin is for and offers a single thing to do next.
 *
 * Promotional rather than a configuration step: no wizard, no redirect. An
 * activation redirect is discouraged on WordPress.org and breaks activating
 * several plugins at once, since the first to hijack the flow leaves the rest
 * half-finished.
 *
 * @since 0.36.0
 */
final class Welcome extends Base {

	/**
	 * Option recording that GatherPress has been activated on this site.
	 *
	 * Set by `Setup::activate_gatherpress_plugin()`, which already loops the
	 * network's sites, so the record is per-site and a network activation
	 * welcomes each site rather than only the one the admin happened to be on.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const OPTION_ACTIVATED = 'gatherpress_activated';

	/**
	 * Unique slug identifying this notice.
	 *
	 * @since 0.36.0
	 *
	 * @return string The slug.
	 */
	public function get_slug(): string {
		return 'gatherpress_welcome';
	}

	/**
	 * The notice's type.
	 *
	 * @since 0.36.0
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type(): string {
		return self::TYPE_INFO;
	}

	/**
	 * Whether the notice can be closed for the current page view.
	 *
	 * False because the card renders its own close control, which records the
	 * dismissal rather than hiding the notice until the next page load. The
	 * native one would sit beside it and look like a second, weaker X.
	 *
	 * @since 0.36.0
	 *
	 * @return bool Always false.
	 */
	public function is_dismissible(): bool {
		return false;
	}

	/**
	 * Whether dismissing the notice is remembered across page loads.
	 *
	 * @since 0.36.0
	 *
	 * @return bool Always true.
	 */
	public function is_persistent(): bool {
		return true;
	}

	/**
	 * Capability required to see the notice.
	 *
	 * The call to action creates an event, so someone who cannot do that has
	 * nothing to act on.
	 *
	 * @since 0.36.0
	 *
	 * @return string The capability.
	 */
	public function get_capability(): string {
		return 'edit_posts';
	}

	/**
	 * Options this notice owns.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] Option names.
	 */
	public function get_options(): array {
		return array( self::OPTION_ACTIVATED );
	}

	/**
	 * Whether this site has activated GatherPress and is on a screen for it.
	 *
	 * Scoped to the plugins screen, where activation lands, and to GatherPress
	 * screens, so someone who navigated straight to Events still finds it.
	 * Everywhere else in the admin it would be someone else's screen.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the welcome should apply.
	 */
	public function applies(): bool {
		return (bool) get_option( self::OPTION_ACTIVATED ) && $this->is_supported_screen();
	}

	/**
	 * Whether the current screen is one this notice belongs on.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True on the plugins screen or a GatherPress screen.
	 */
	protected function is_supported_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		// The post type checks rather than a slug match, so a companion
		// plugin's own event or venue post type counts as a GatherPress
		// screen too.
		return 'plugins' === $screen->id
			|| str_contains( $screen->id, 'gatherpress' )
			|| post_type_supports( (string) $screen->post_type, 'gatherpress-event-date' )
			|| post_type_supports( (string) $screen->post_type, 'gatherpress-venue-information' );
	}

	/**
	 * The notice's message.
	 *
	 * One sentence on what GatherPress is for. Deliberately not a feature
	 * list: someone who has just activated it has not asked for one yet.
	 *
	 * @since 0.36.0
	 *
	 * @return string The translated message.
	 */
	public function get_message(): string {
		return esc_html__(
			'GatherPress brings event management to WordPress, built by and for the communities that use it.',
			'gatherpress'
		);
	}

	/**
	 * The headline confirming the install.
	 *
	 * @since 0.36.0
	 *
	 * @return string The translated headline.
	 */
	public function get_headline(): string {
		return esc_html__( 'You have successfully installed GatherPress!', 'gatherpress' );
	}

	/**
	 * Where the call to action goes.
	 *
	 * Creating an event is the thing GatherPress exists to do, and it teaches
	 * more about the plugin than any amount of reading would.
	 *
	 * @since 0.36.0
	 *
	 * @return string The admin URL for a new event.
	 */
	public function get_action_url(): string {
		return admin_url( sprintf( 'post-new.php?post_type=%s', Event::POST_TYPE ) );
	}

	/**
	 * What the call to action reads.
	 *
	 * @since 0.36.0
	 *
	 * @return string The translated label.
	 */
	public function get_action_label(): string {
		return esc_html__( 'Create your first event', 'gatherpress' );
	}
}
