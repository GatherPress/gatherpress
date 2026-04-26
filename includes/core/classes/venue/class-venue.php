<?php
/**
 * Per-post instance class for a single Venue.
 *
 * Mirrors the {@see \GatherPress\Core\Event\Event} class: constructed around a specific
 * venue post ID, populates `$this->venue` with the WP_Post when the post type declares
 * `gatherpress-venue-information` support, and exposes accessors for that
 * venue's stored information, taxonomy term, and slug. Everything not tied to
 * a specific venue instance — post type registration, taxonomy helpers,
 * event→venue lookups — lives on {@see Setup}.
 *
 * @package GatherPress\Core\Venue
 * @since 1.0.0
 */

namespace GatherPress\Core\Venue;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Utility;
use WP_Post;
use WP_Term;

/**
 * Class Venue.
 *
 * Instance anchored to a specific venue post ID. Pair with {@see Setup} for
 * the WordPress integration layer and venue-type-level utilities.
 *
 * @since 1.0.0
 */
class Venue {
	/**
	 * Default venue post type slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const POST_TYPE = 'gatherpress_venue';

	/**
	 * Default venue taxonomy slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TAXONOMY = '_gatherpress_venue';

	/**
	 * Venue post object.
	 *
	 * Null when the post_id passed to the constructor does not resolve to a
	 * post whose post type declares `gatherpress-venue-information` support.
	 *
	 * @since 1.0.0
	 * @var WP_Post|null
	 */
	public ?WP_Post $venue = null;

	/**
	 * Construct a Venue around a specific post ID.
	 *
	 * Only populates `$this->venue` when the post type declares
	 * `gatherpress-venue-information` support, so callers can guard on
	 * `$venue->venue instanceof WP_Post` to tell a legit venue from a
	 * stale/mistyped ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 */
	public function __construct( int $post_id ) {
		if ( post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-venue-information' ) ) {
			$this->venue = get_post( $post_id );
		}
	}

	/**
	 * Returns the venue post ID, or 0 when this instance does not wrap a real venue.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_post_id(): int {
		return $this->venue instanceof WP_Post ? $this->venue->ID : 0;
	}

	/**
	 * Returns the venue post type slug (e.g. `gatherpress_venue`).
	 *
	 * Empty string when this instance does not wrap a real venue.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_post_type(): string {
		return $this->venue instanceof WP_Post ? $this->venue->post_type : '';
	}

	/**
	 * Returns the taxonomy slug that backs this venue's term.
	 *
	 * Derived from the venue's post type via {@see Setup::get_taxonomy()}.
	 * Empty string when this instance does not wrap a real venue.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_taxonomy(): string {
		if ( ! $this->venue instanceof WP_Post ) {
			return '';
		}

		return Setup::get_instance()->get_taxonomy( $this->venue->post_type );
	}

	/**
	 * Returns the venue taxonomy term slug for this venue.
	 *
	 * Format is the underscore-prefixed post_name (e.g. `my-venue` →
	 * `_my-venue`). Delegates the formatting to
	 * {@see Setup::term_slug_from_post_name()} so there is only one source
	 * of truth for the slug shape. Empty string when this instance does not
	 * wrap a real venue.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_term_slug(): string {
		if ( ! $this->venue instanceof WP_Post ) {
			return '';
		}

		return Setup::get_instance()->term_slug_from_post_name( $this->venue->post_name );
	}

	/**
	 * Returns the taxonomy term associated with this venue, if one exists.
	 *
	 * Resolves `$this->get_term_slug()` in `$this->get_taxonomy()`. Returns
	 * null when the term hasn't been created yet (e.g. during the save
	 * transition before `add_venue_term` has run) or when this instance does
	 * not wrap a real venue.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Term|null
	 */
	public function get_term(): ?WP_Term {
		if ( ! $this->venue instanceof WP_Post ) {
			return null;
		}

		$term = get_term_by( 'slug', $this->get_term_slug(), $this->get_taxonomy() );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Returns the venue information for this venue.
	 *
	 * Reads the editor-writable venue meta keys and returns them with
	 * empty-string fallbacks so callers can treat the array shape as stable.
	 * Also returns the empty shape when this instance does not wrap a real
	 * venue.
	 *
	 * The structured-address fields (`city`, `country`, `country_code`,
	 * `county`, `house_number`, `postcode`, `state`, `street`) are populated
	 * by the address-autocomplete handler when the user picks a suggestion;
	 * freeform-typed addresses leave them blank. Callers that consume the
	 * structured pieces (e.g. schema.org / JSON-LD emitters) should treat
	 * empty values as "not set" rather than retry-geocoding.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     address: string,
	 *     city: string,
	 *     country: string,
	 *     country_code: string,
	 *     county: string,
	 *     house_number: string,
	 *     latitude: string,
	 *     longitude: string,
	 *     phone: string,
	 *     postcode: string,
	 *     state: string,
	 *     street: string,
	 *     website: string
	 * }
	 */
	public function get_information(): array {
		$fields      = array(
			'address',
			'city',
			'country',
			'country_code',
			'county',
			'house_number',
			'latitude',
			'longitude',
			'phone',
			'postcode',
			'state',
			'street',
			'website',
		);
		$information = array_fill_keys( $fields, '' );

		if ( ! $this->venue instanceof WP_Post ) {
			return $information;
		}

		// Read once and pluck — get_post_meta( $id ) primes the meta cache and
		// returns every key in a single call, avoiding N sequential lookups.
		$all_meta = get_post_meta( $this->venue->ID );

		foreach ( $fields as $field ) {
			$meta_key              = Utility::prefix_key( $field );
			$information[ $field ] = isset( $all_meta[ $meta_key ][0] )
				? (string) $all_meta[ $meta_key ][0]
				: '';
		}

		return $information;
	}
}
