<?php
/**
 * Owns the per-event checklist.
 *
 * A checklist is an ordered list of items stored as a single JSON string in
 * the `gatherpress_checklist` post meta. Each item carries a stable `id`, the
 * `text` shown in the editor, and a `completed` flag, so a stored list keeps
 * its identity while items are renamed or ticked off.
 *
 * The list is deliberately readable in the REST `edit` context only. Event
 * checklists are used for organizer-side work such as compliance reviews and
 * invoice handling, so the data must not ride along on a public post read.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Checklist.
 *
 * Singleton owning the checklist post type support and the JSON meta that
 * backs it. Hooks `registered_post_type` so any post type declaring
 * `gatherpress-event-checklist`, including companion-plugin types, gets the
 * same meta shape and sanitizer.
 *
 * @since 0.36.0
 */
final class Checklist {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Post type support that gives a post type a checklist.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SUPPORT = 'gatherpress-event-checklist';

	/**
	 * Post meta key holding the JSON-encoded checklist.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const META_KEY = 'gatherpress_checklist';

	/**
	 * Empty checklist, used as the meta default and the sanitizer fallback.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const EMPTY_CHECKLIST = '[]';

	/**
	 * Maximum number of items kept in a checklist.
	 *
	 * A checklist is a working list for one event, not a data store. The cap
	 * keeps a malformed or hostile payload from writing an unbounded string
	 * into a single meta row.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MAX_ITEMS = 200;

	/**
	 * Maximum number of characters kept per item.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MAX_TEXT_LENGTH = 255;

	/**
	 * Maximum number of characters kept in an item id.
	 *
	 * An id only has to identify a row while it is rewritten, so it does not
	 * need to carry content. The cap sits well above the editor's UUIDs (36
	 * characters) and above hand-written ids such as `inquiry`, but keeps a
	 * REST write from parking a near-request-sized string in a meta row.
	 * Format is deliberately not validated: any stable id is allowed.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MAX_ID_LENGTH = 64;

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for checklist registration.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'register' ) );
	}

	/**
	 * Register the checklist meta on a post type that declares the support.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function register( string $post_type ): void {
		if ( ! post_type_supports( $post_type, self::SUPPORT ) ) {
			return;
		}

		// `WP_REST_Posts_Controller` only attaches the `meta` field to a post
		// type's REST schema when the post type declares `custom-fields`
		// support. Without it the editor's autosave silently drops the
		// checklist, so force the support on rather than making every
		// companion post type declare it.
		add_post_type_support( $post_type, 'custom-fields' );

		// Restricted to the `edit` context: a checklist can hold payment and
		// compliance notes, so it is left out of public post reads even
		// though an anonymous visitor can read the rest of the event.
		register_post_meta(
			$post_type,
			self::META_KEY,
			array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => array(
					'schema' => array(
						'context' => array( 'edit' ),
					),
				),
				'single'            => true,
				'type'              => 'string',
				'default'           => self::EMPTY_CHECKLIST,
			)
		);
	}

	/**
	 * Sanitize a checklist payload.
	 *
	 * Anything that is not a JSON array of usable items collapses to an empty
	 * checklist, so a malformed write cannot strand the editor on data it
	 * cannot parse.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $value Raw meta value as submitted.
	 *
	 * @return string JSON-encoded checklist.
	 */
	public function sanitize( $value ): string {
		if ( ! is_string( $value ) ) {
			return self::EMPTY_CHECKLIST;
		}

		$decoded = json_decode( $value, true );

		// `json_decode()` with associative mode turns a JSON object into a PHP
		// array, so `is_array()` alone would accept `{"row":{...}}` and rewrite
		// it as a list. Requiring a list keeps associative containers on the
		// malformed-payload fallback instead of silently reindexing them.
		if ( ! is_array( $decoded ) || ! array_is_list( $decoded ) ) {
			return self::EMPTY_CHECKLIST;
		}

		$items = array();

		foreach ( $decoded as $item ) {
			if ( self::MAX_ITEMS === count( $items ) ) {
				break;
			}

			$sanitized = $this->sanitize_item( $item );

			if ( null !== $sanitized ) {
				$items[] = $sanitized;
			}
		}

		$encoded = wp_json_encode( $items );

		return false === $encoded ? self::EMPTY_CHECKLIST : $encoded;
	}

	/**
	 * Sanitize a single checklist item.
	 *
	 * Returns null for entries that are not shaped like an item, which is how
	 * they get dropped from the stored list. An item with an empty `text` is
	 * kept: the editor writes on every keystroke, so a row the author has just
	 * added and not yet typed into must survive the round trip.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $item Raw item as submitted.
	 *
	 * @return array{id: string, text: string, completed: bool}|null Sanitized item, or null when unusable.
	 */
	protected function sanitize_item( $item ): ?array {
		if ( ! is_array( $item ) || ! isset( $item['id'] ) || ! is_scalar( $item['id'] ) ) {
			return null;
		}

		$id = sanitize_text_field( (string) $item['id'] );

		if ( '' === $id || mb_strlen( $id ) > self::MAX_ID_LENGTH ) {
			return null;
		}

		$text = isset( $item['text'] ) && is_scalar( $item['text'] )
			? sanitize_text_field( (string) $item['text'] )
			: '';

		// `rest_sanitize_boolean()` is generic, so PHPStan cannot infer its
		// type argument from a mixed array value. Casting to string first
		// keeps the same outcome for every scalar ('1' is true, '0' and
		// 'false' are false) while giving the analyzer a type it can resolve.
		$completed = $item['completed'] ?? false;
		$completed = is_scalar( $completed ) ? (string) $completed : '';

		return array(
			'id'        => $id,
			'text'      => mb_substr( $text, 0, self::MAX_TEXT_LENGTH ),
			'completed' => rest_sanitize_boolean( $completed ),
		);
	}
}
