<?php
/**
 * Class is responsible for all email related functionality.
 *
 * @package GatherPress
 * @subpackage Includes
 * @since 1.0.0
 */

namespace GatherPress\Includes\BuddyPress;

use \GatherPress\Includes\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email.
 */
class Email {

	use Singleton;

	/**
	 * BuddyPress constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'admin_init', array( $this, 'setup_email_templates' ) );
	}

	/**
	 * Basically a copy of bp_core_install_emails() for our creation of custom templates.
	 */
	public function setup_email_templates() {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		$key          = 'gp_email_templates';
		$templates    = get_option( $key, array() );
		$defaults     = array(
			'post_status' => 'publish',
			'post_type'   => bp_get_email_post_type(),
		);
		$emails       = $this->email_get_schema();
		$descriptions = $this->email_get_type_schema( 'description' );
		$cache_key    = md5( wp_json_encode( $emails ) ) . md5( wp_json_encode( $descriptions ) );

		if ( $templates['cache_key'] === $cache_key ) {
			return;
		}

		$templates['cache_key'] = $cache_key;

		// Add these emails to the database.
		foreach ( $emails as $id => $email ) {
			// Some emails are multisite-only.
			if ( ! is_multisite() && isset( $email['args'] ) && ! empty( $email['args']['multisite'] ) ) {
				continue;
			}

			$args = bp_parse_args( $email, $defaults, 'install_email_' . $id );

			if (
				! intval( $templates[ $id ] )
				|| false === get_post_status( $templates[ $id ] )
			) {
				$post_id = wp_insert_post( $args );
			}

			if ( ! $post_id ) {
				continue;
			}

			$templates[ $id ] = $post_id;

			$tt_ids = wp_set_object_terms( $post_id, $id, bp_get_email_tax_type() );

			foreach ( $tt_ids as $tt_id ) {
				$term = get_term_by( 'term_taxonomy_id', (int) $tt_id, bp_get_email_tax_type() );

				wp_update_term(
					(int) $term->term_id,
					bp_get_email_tax_type(),
					array(
						'description' => $descriptions[ $id ],
					)
				);
			}
		}

		update_option( $key, $templates );
	}

	/**
	 * Create GatherPress email schema for BuddyPress email templates.
	 *
	 * @return array[]
	 */
	protected function email_get_schema() : array {
		return array(
			'gp-event-announce' => array(
				'post_title'   => __( '[{{{site.name}}}] posted new event', 'gatherpress' ),
				'post_content' => __( "{{event.name}} has been announced:\n\n<a href=\"{{{event.url}}}\">Go to the event page</a>.", 'gatherpress' ),
				'post_excerpt' => __( "{{event.name}} has been announced:\n\n<a href=\"{{{event.url}}}\">Go to the event page</a>.", 'gatherpress' ),
			),
		);
	}

	/**
	 * Method to get the email type schema for GatherPress that utilizes BuddyPress functionality.
	 *
	 * @param string $field The specific field to return in array if not `all`.
	 *
	 * @return array[]
	 */
	protected function email_get_type_schema( string $field = 'description' ) : array {
		$types = array(
			'gatherpress-event-announce' => array(
				'description' => __( 'A new event was announced.', 'gatherpress' ),
				'unsubscribe' => array(
					'meta_key' => 'notification_event_announce', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'message'  => __( 'You will no longer receive emails when one of your groups announces an event.', 'gatherpress' ),
				),
			),
		);

		if ( 'all' !== $field ) {
			return wp_list_pluck( $types, $field );
		}

		return $types;
	}

	/**
	 * Announce new event to all members.
	 *
	 * @todo will need to send this to a queue to process for large groups.
	 *
	 * @param int $post_id An event post ID.
	 *
	 * @return bool
	 */
	public function event_announce( int $post_id ) : bool {
		$setting = 'gatherpress-event-announce';
		$meta    = get_post_meta( $post_id, $setting, true );
		$status  = get_post_status( $post_id );
		$event   = new Event( $post_id );

		if (
			! empty( $meta )
			|| 'publish' !== $status
			|| $event->has_event_past() ) {
			return false;
		}

		$users = get_users();

		foreach ( $users as $user ) {
			if ( 'no' === bp_get_user_meta( (int) $user->ID, 'notification_event_announce', true ) ) {
				continue;
			}

			$unsubscribe_args = array(
				'user_id'           => $user->ID,
				'notification_type' => $setting,
			);

			$args = array(
				'tokens' => array(
					'site.name'   => esc_html( bp_get_site_name() ),
					'event.name'  => esc_html( get_the_title( $post_id ) ),
					'event.url'   => esc_url( get_the_permalink( $post_id ) ),
					'unsubscribe' => esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) ),
				),
			);

			bp_send_email( $setting, $user->ID, $args );
		}

		update_post_meta( $post_id, $setting, time() );

		return true;
	}

}
