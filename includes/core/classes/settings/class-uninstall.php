<?php
/**
 * Uninstall settings page for GatherPress.
 *
 * Declares what deleting the plugin is allowed to remove.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Settings;
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

/**
 * Class Uninstall.
 *
 * The opt-in screen for the destructive uninstall tasks. Everything here is
 * off until an administrator turns it on, so deleting the plugin removes
 * nothing a reinstall would want back unless that was asked for.
 *
 * Every option is declared `'exportable' => false`. A settings export
 * leaves them out and an import refuses them, because every other setting
 * shows its effect as soon as it is wrong, while these do nothing until the
 * plugin is deleted, possibly months later and by somebody who never ran
 * the import, and what they remove is gone.
 *
 * On multisite the page appears on the network settings screen like any
 * other, so a network administrator can either set one answer for every
 * site, by adding these keys to the inheritance list on the Network tab, or
 * leave them off it and let each site answer for its own data.
 *
 * @since 0.36.0
 *
 * @phpstan-import-type SettingsOption from Settings
 * @phpstan-import-type SettingsSection from Settings
 */
final class Uninstall extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'admin_notices', array( $this, 'render_warning' ) );
	}

	/**
	 * Warn what deleting the plugin costs, on this screen and no other.
	 *
	 * Not dismissible. It is the whole subject of the page, and somebody
	 * who came here to change what gets deleted should read it every time
	 * rather than once.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function render_warning(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( Utility::prefix_key( $this->slug ) !== $page ) {
			return;
		}

		$export = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'export.php' ) ),
			esc_html__( 'Tools > Export', 'gatherpress' )
		);

		wp_admin_notice(
			sprintf(
				'<strong>%1$s</strong> %2$s',
				esc_html__(
					// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
					'What you select below is permanently deleted when the plugin is deleted.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				sprintf(
					/* translators: %s: Link to the WordPress export screen, reading "Tools > Export". */
					esc_html__(
						// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
						'Deleting the plugin on its own removes nothing, and deactivating never does. There is no undo, so back up first. Export your GatherPress settings from the Tools tab, and export your events and venues from %s.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					$export
				)
			),
			array(
				'type'        => 'warning',
				'dismissible' => false,
			)
		);
	}

	/**
	 * Get the slug for the uninstall settings page.
	 *
	 * @since 0.36.0
	 *
	 * @return string The slug for the uninstall settings page.
	 */
	protected function get_slug(): string {
		return 'uninstall_settings';
	}

	/**
	 * Get the name for the uninstall settings page.
	 *
	 * @since 0.36.0
	 *
	 * @return string The localized name for the uninstall settings page.
	 */
	protected function get_name(): string {
		return __( 'Uninstall', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the uninstall settings page.
	 *
	 * At the end of the list, after Tools and before Credits. Nothing an
	 * administrator reaches for day to day should sit in front of it, and
	 * Credits stays last where people expect to find it.
	 *
	 * The three tail pages hold distinct priorities rather than sharing
	 * one, so their order does not depend on the sort being stable.
	 *
	 * @since 0.36.0
	 *
	 * @return int The priority for displaying the uninstall settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX - 1;
	}

	/**
	 * Where this screen lives.
	 *
	 * @since 0.36.0
	 *
	 * @return string The admin URL of the uninstall settings page.
	 */
	public static function get_page_url(): string {
		return add_query_arg(
			array(
				'post_type' => Event::POST_TYPE,
				'page'      => Utility::prefix_key( self::get_instance()->slug ),
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * The names of the tasks this site has opted in to.
	 *
	 * Read from the same declarations the screen renders, so a warning
	 * elsewhere in the admin names the rows in the words the administrator
	 * saw when they ticked them.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The labels, in the order the screen lists them.
	 */
	public static function armed_labels(): array {
		$options = self::get_instance()->get_task_options();
		$armed   = array();

		foreach ( Preferences::task_keys() as $task ) {
			if ( ! Preferences::is_enabled( $task ) ) {
				continue;
			}

			$key = Preferences::option_key( $task );

			$armed[] = (string) ( $options[ $key ]['labels']['name'] ?? $task );
		}

		return $armed;
	}

	/**
	 * Get the sections for the uninstall settings page.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, SettingsSection> The sections for the uninstall settings page.
	 */
	protected function get_sections(): array {
		return array(
			'uninstall' => array(
				'name'        => __( 'What to remove', 'gatherpress' ),
				'description' => $this->get_section_description(),
				'options'     => $this->get_task_options(),
			),
		);
	}

	/**
	 * The copy that sits above the choices.
	 *
	 * @since 0.36.0
	 *
	 * @return string The description, with the markup `wp_kses_post()` allows.
	 */
	protected function get_section_description(): string {
		return sprintf(
			'%1$s %2$s',
			esc_html__( 'Choose what GatherPress removes when you delete the plugin.', 'gatherpress' ),
			sprintf(
				/* translators: %s: The WP-CLI command that deletes the plugin, in a code element. */
				esc_html__(
					// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
					'On a large site, delete with WP-CLI: %s. A browser request can hit the web server time limit and stop part way through.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				'<code>wp plugin uninstall gatherpress --deactivate</code>'
			)
		);
	}

	/**
	 * One checkbox per uninstall task.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, SettingsOption> The options for the section.
	 */
	protected function get_task_options(): array {
		$events = Utility::post_type_label( 'name', Event::POST_TYPE );
		$venues = Utility::post_type_label( 'name', Venue::POST_TYPE );
		$topics = Utility::taxonomy_label( 'name', Topic::TAXONOMY );

		$options = array(
			Preferences::TASK_EVENTS  => array(
				'name'        => $events,
				/* translators: %s: Plural post type label (e.g. "Events"). */
				'label'       => sprintf( __( 'Remove %s', 'gatherpress' ), $events ),
				'description' => sprintf(
					/* translators: %s: Plural post type label (e.g. "Events"). */
					__(
						// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
						'Removes all %s with their meta and revisions, the RSVPs recorded against them, and the table that holds their dates.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					$events
				),
			),
			Preferences::TASK_RSVPS   => array(
				'name'        => __( 'RSVPs', 'gatherpress' ),
				'label'       => __( 'Remove RSVPs', 'gatherpress' ),
				'description' => __(
					// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
					'Removes every RSVP record, the answers people gave to custom RSVP fields, and the internal terms that track each response.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				// Removing events removes the RSVPs on them either way, so the
				// choice is only offered while events are staying. An unchecked
				// checkbox reads as '' here and as false in the browser, which
				// is what the two accepted values cover.
				'show_if'     => array(
					Preferences::option_key( Preferences::TASK_EVENTS ) => '',
				),
			),
			Preferences::TASK_TOPICS  => array(
				'name'        => $topics,
				/* translators: %s: Plural taxonomy label (e.g. "Topics"). */
				'label'       => sprintf( __( 'Remove %s', 'gatherpress' ), $topics ),
				'description' => sprintf(
					/* translators: %s: Plural taxonomy label (e.g. "Topics"). */
					__(
						// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
						'Removes all %s and the way they were assigned. They are kept by default, because a vocabulary you built can outlive the plugin.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					$topics
				),
			),
			Preferences::TASK_VENUES  => array(
				'name'        => $venues,
				/* translators: %s: Plural post type label (e.g. "Venues"). */
				'label'       => sprintf( __( 'Remove %s', 'gatherpress' ), $venues ),
				'description' => sprintf(
					/* translators: 1: Plural post type label ("Venues"), 2: Plural post type label ("Events"). */
					__(
						// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
						'Removes all %1$s with their meta and revisions, and the internal terms GatherPress keeps to connect %2$s to them.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					$venues,
					$events
				),
			),
			Preferences::TASK_FILES   => array(
				'name'        => __( 'Generated files', 'gatherpress' ),
				'label'       => __( 'Remove generated files', 'gatherpress' ),
				'description' => sprintf(
					/* translators: %s: Plural post type label (e.g. "Venues"). */
					__(
						// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
						'Deletes the files GatherPress generated in your uploads folder, such as the static maps for %s. They are not in your media library.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					$venues
				),
			),
			Preferences::TASK_CRON    => array(
				'name'        => __( 'Scheduled jobs', 'gatherpress' ),
				'label'       => __( 'Remove scheduled jobs', 'gatherpress' ),
				'description' => __(
					'Clears the scheduled tasks the plugin registers, such as RSVP cleanup and map generation.',
					'gatherpress'
				),
			),
			Preferences::TASK_USERS   => array(
				'name'        => __( 'User preferences', 'gatherpress' ),
				'label'       => __( 'Remove user preferences', 'gatherpress' ),
				'description' => __(
					// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
					'Removes what each person chose for themselves: their time zone and time format, whether they receive event updates, and their RSVP screen options.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
			),
			Preferences::TASK_OPTIONS => array(
				'name'        => __( 'Settings', 'gatherpress' ),
				'label'       => __( 'Remove settings', 'gatherpress' ),
				'description' => __(
					'Removes the GatherPress settings, including the choices on this screen.',
					'gatherpress'
				),
			),
		);

		$declared = array();

		foreach ( $options as $task => $option ) {
			$declared[ Preferences::option_key( (string) $task ) ] = array(
				'labels'      => array( 'name' => $option['name'] ),
				'description' => $option['description'],
				'field'       => array(
					'label'   => $option['label'],
					'type'    => 'checkbox',
					'options' => array( 'default' => false ),
				),
				// Never travels in a settings file, and an import will not
				// write it. See the class docblock for why.
				'exportable'  => false,
			) + ( isset( $option['show_if'] ) ? array( 'show_if' => $option['show_if'] ) : array() );
		}

		return $declared;
	}
}
