<?php
/**
 * Handles the registration of Event REST API endpoints.
 *
 * This file contains the Rest_Api class, which is responsible for registering and managing
 * various Event REST API endpoints within the GatherPress plugin.
 *
 * @package GatherPress\Core\Event
 * @since 0.27.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Blocks\Rsvp_Template;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrence_Identity;
use GatherPress\Core\Rsvp\Form;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Setup;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\User;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;
use WP_Comment;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

/**
 * Class Rest_Api.
 *
 * Responsible for registering and managing various REST API endpoints used
 * by the GatherPress plugin. It provides methods for defining routes,
 * handling requests, and delivering responses via the WordPress REST API
 * infrastructure.
 *
 * @since 0.34.0
 *
 * @phpstan-type RouteDefinition array{route: string, args: array<string, mixed>}
 * @phpstan-type SendOptions array{all: bool, attending: bool, waiting_list: bool, not_attending: bool}
 * @phpstan-type Recipient array{is_user: bool, user_id: int, comment_id: int, email: string, name: string}
 */
final class Rest_Api {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Request parameter naming the occurrence an RSVP route operates on.
	 *
	 * Optional on every route that accepts it. Absent means the series, which
	 * is what every one of these routes has always meant, so an unmodified
	 * client keeps its exact behavior.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RECURRENCE_ID_PARAM = 'recurrence_id';

	/**
	 * Occurrence-context frames, innermost last.
	 *
	 * Each frame is `array( 'request' => WP_REST_Request, 'previous' => array|null )`:
	 * the dispatch that entered a context, and whatever context was standing
	 * when it did. A stack rather than a single slot because REST dispatch
	 * nests. `rsvp_status_html()` renders arbitrary block content, any of which
	 * may call `rest_do_request()` for a different occurrence of the same
	 * series. With one slot the inner dispatch overwrote the outer request's
	 * identity, and its teardown then cleared the context outright, so the outer
	 * route silently finished series-wide and never tore down at all.
	 *
	 * @since 0.36.0
	 * @var array<int, array{request: WP_REST_Request, previous: array|null}>
	 */
	protected array $occurrence_frames = array();

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_action( 'gatherpress_send_emails', array( $this, 'handle_email_send_action' ), 10, 4 );
		add_filter( sprintf( 'rest_prepare_%s', Event::POST_TYPE ), array( $this, 'prepare_event_data' ) );
	}

	/**
	 * Registers REST API endpoints for GatherPress events.
	 *
	 * Registers various REST API endpoints for interacting with GatherPress events.
	 * The registered routes include endpoints for event creation, retrieval, updating, and deletion.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		// All event routes.
		$routes = $this->get_event_routes();

		foreach ( $routes as $route ) {
			register_rest_route(
				sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
				sprintf( '/%s', $route['route'] ),
				$route['args']
			);
		}
	}

	/**
	 * Get the event routes.
	 *
	 * Retrieves an array of REST API routes for GatherPress events.
	 *
	 * @since 0.34.0
	 *
	 * @return array<int, RouteDefinition> An array of route definitions for GatherPress events.
	 */
	protected function get_event_routes(): array {
		return array(
			$this->email_route(),
			$this->rsvp_route(),
			$this->rsvp_form_route(),
			$this->rsvp_status_html_route(),
			$this->rsvp_responses_route(),
			$this->nonce_route(),
		);
	}

	/**
	 * Define the REST route for sending event-related emails.
	 *
	 * This method sets up the REST route for sending emails related to an event.
	 *
	 * @since 0.34.0
	 *
	 * @return RouteDefinition The REST route configuration.
	 */
	protected function email_route(): array {
		return array(
			'route' => 'email',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'email' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					// Per-post check: only users who can edit *this* event may
					// send emails about it. Mirrors the meta auth_callback
					// model so a non-owner Author can't blast emails about
					// someone else's event via this route.
					return current_user_can( Event::EDIT_CAPABILITY, (int) $request['post_id'] );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'message' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'subject' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'send'    => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'send' ),
					),
				),
			),
		);
	}

	/**
	 * Define REST API route for generating nonce.
	 *
	 * Creates a publicly accessible endpoint that generates a fresh nonce
	 * for authenticated REST API requests.
	 *
	 * @since 0.34.0
	 *
	 * @return RouteDefinition Route configuration array.
	 */
	protected function nonce_route(): array {
		return array(
			'route' => 'nonce',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					// Short-term caching (30 seconds) to prevent endpoint hammering while maintaining security.
					// WordPress nonces are valid for ~12 hours, so 30 seconds of caching has no UX impact
					// but protects against rapid successive requests that could overwhelm the server.
					header( 'Cache-Control: private, max-age=30' );
					header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 30 ) . ' GMT' );

					// Ensure proper user authentication for nonce generation.
					Utility::ensure_user_authentication();

					$response = array(
						'nonce' => wp_create_nonce( 'wp_rest' ),
					);

					return new WP_REST_Response( $response );
				},
				'permission_callback' => '__return_true',
			),
		);
	}

	/**
	 * Define the REST route for updating event RSVP status.
	 *
	 * This method sets up the REST route for updating the RSVP status of an event.
	 *
	 * @since 0.34.0
	 *
	 * @return RouteDefinition The REST route configuration.
	 */
	protected function rsvp_route(): array {
		return array(
			'route' => 'rsvp',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_rsvp' ),
				'permission_callback' => array( $this, 'can_update_rsvp' ),
				'args'                => array(
					'post_id'                 => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'rsvp_token'              => array(
						'required'          => false,
						'validate_callback' => static function ( $param ): bool {
							return ! empty( Token::parse_token_string( $param ) );
						},
					),
					'status'                  => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'rsvp_status' ),
					),
					self::RECURRENCE_ID_PARAM => $this->recurrence_id_arg(),
				),
			),
		);
	}

	/**
	 * Define the REST route for handling RSVP form submissions via Ajax.
	 *
	 * This method sets up the REST route for processing RSVP form submissions
	 * dynamically via Ajax while maintaining the same functionality as the
	 * traditional comment-based form submission system.
	 *
	 * @since 0.34.0
	 *
	 * @return RouteDefinition The REST route configuration.
	 */
	protected function rsvp_form_route(): array {
		return array(
			'route' => 'rsvp-form',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'handle_rsvp_form_submission' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'comment_post_ID'                  => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'author'                           => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return ! empty( sanitize_text_field( $param ) );
						},
					),
					'email'                            => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
					'gatherpress_form_schema_id'       => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^form_\d+$/', $param );
						},
					),
					'gatherpress_event_updates_opt_in' => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'boolean' ),
					),
					'gatherpress_rsvp_form_guests'     => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'non_negative_number' ),
					),
					'gatherpress_rsvp_form_anonymous'  => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'boolean' ),
					),
					self::RECURRENCE_ID_PARAM          => $this->recurrence_id_arg(),
				),
			),
		);
	}

	/**
	 * Define the REST route for rendering RSVP block HTML.
	 *
	 * This method registers a REST API route for dynamically generating HTML markup
	 * for RSVP blocks based on the provided block data and post ID.
	 * The generated HTML reflects the current RSVP status and can be used
	 * to re-render block content when status changes occur.
	 *
	 * @since 0.34.0
	 *
	 * @return RouteDefinition The REST route configuration.
	 */
	protected function rsvp_status_html_route(): array {
		return array(
			'route' => 'rsvp-status-html',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rsvp_status_html' ),
				'permission_callback' => array( $this, 'can_read_event_rsvps' ),
				'args'                => array(
					'post_id'                 => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'status'                  => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'rsvp_status' ),
					),
					'block_data'              => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'block_data' ),
					),
					'limit_enabled'           => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'boolean' ),
					),
					'limit'                   => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'positive_number' ),
					),
					self::RECURRENCE_ID_PARAM => $this->recurrence_id_arg(),
				),
			),
		);
	}

	/**
	 * Get route configuration for RSVP responses endpoint.
	 *
	 * Defines REST route configuration to fetch RSVP response data for an event post.
	 * Endpoint requires post_id parameter which must validate as an event post type.
	 *
	 * @since 0.34.0
	 *
	 * @return RouteDefinition Route configuration with path, methods, callback and arguments.
	 */
	protected function rsvp_responses_route(): array {
		return array(
			'route' => 'rsvp-responses',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rsvp_responses' ),
				'permission_callback' => array( $this, 'can_read_event_rsvps' ),
				'args'                => array(
					'post_id'                 => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					self::RECURRENCE_ID_PARAM => $this->recurrence_id_arg(),
				),
			),
		);
	}

	/**
	 * Build the shared optional `recurrence_id` argument definition.
	 *
	 * Optional everywhere. An absent argument means the series, the behavior
	 * every one of these routes has always had. An unmodified client is
	 * therefore unaffected, and a site with no recurring events never reaches
	 * any of the occurrence machinery at all.
	 *
	 * @since 0.36.0
	 *
	 * @return array The argument definition, in `register_rest_route()` shape.
	 */
	protected function recurrence_id_arg(): array {
		return array(
			'required'          => false,
			'validate_callback' => array( $this, 'validate_recurrence_id' ),
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Reject a `recurrence_id` that is not a canonical occurrence identifier.
	 *
	 * WordPress only runs a validate callback for a parameter the request
	 * actually carries, so anything reaching here is a caller naming an
	 * occurrence.
	 *
	 * **This check is deliberately syntax-only, and that is a security
	 * property rather than a simplification.** WordPress runs validate
	 * callbacks inside `WP_REST_Request::has_valid_params()`, which is *before*
	 * the route's `permission_callback`. A validator that resolved the
	 * identifier against storage therefore answered "is this a real occurrence
	 * of that post?" for a caller who had not been authorized to ask, and the
	 * 400-versus-401 split let an unauthenticated visitor enumerate the
	 * schedule of a draft or private event one candidate at a time. Syntax is
	 * the most that can be checked here without disclosure, because a malformed
	 * string is refusable from the string alone.
	 *
	 * Membership is still enforced, one step later and after authorization, by
	 * `enter_occurrence_context()`. **An unresolvable occurrence still fails
	 * closed there**, and the alternative remains worse in both directions. On
	 * a read, falling back to the series would hand a caller who asked for one
	 * date the entire series' attendee list, including names and emails they
	 * did not ask for. On a write it is worse still: the responder believes
	 * they have booked September 17th, the RSVP lands series-wide with no
	 * occurrence term, and there is no error anywhere and no way to tell it
	 * apart afterwards from a deliberate series RSVP. Silent widening of a
	 * scope the caller narrowed is not a graceful degradation; it is the
	 * data-corruption mode this whole subsystem is built to avoid.
	 *
	 * The remaining accepted cost is staleness. A page cached by a reverse
	 * proxy, or a rule edited between render and click, yields a 404 where the
	 * request would previously have succeeded. That is correct, because the
	 * occurrence the page named genuinely no longer exists, and it is a
	 * recoverable client error that a reload fixes.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $param The submitted value, before sanitization.
	 *
	 * @return bool True when the identifier is a canonical `Ymd\THis` string.
	 */
	public function validate_recurrence_id( $param ): bool {
		return Occurrence_Identity::is_canonical( sanitize_text_field( (string) $param ) );
	}

	/**
	 * Read the event post ID a request names, whichever parameter carries it.
	 *
	 * The RSVP form route inherits `comment_post_ID` from the comment form it
	 * replaced; every other RSVP route uses `post_id`. The two are mutually
	 * exclusive per route, and `get_param()` reads undeclared parameters too,
	 * so a route that declares `comment_post_ID` reads exactly that parameter:
	 * a stray `post_id` posted alongside the form (a theme's hidden field, a
	 * stale embed) must neither suppress the declared parameter when it is
	 * zero nor override it when it is not. The override was the sharper half:
	 * the form route's viewability gate authorizes the post that
	 * `comment_post_ID` names, and a smuggled non-zero `post_id` resolved the
	 * occurrence inside a series that gate never looked at. On every other
	 * route `post_id` is read as given, with the `comment_post_ID` fallback
	 * firing whenever `post_id` names no post.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 *
	 * @return int The event post ID, or 0 when the request names none.
	 */
	private function request_post_id( WP_REST_Request $request ): int {
		$declared = (array) ( $request->get_attributes()['args'] ?? array() );

		if ( array_key_exists( 'comment_post_ID', $declared ) ) {
			return (int) $request->get_param( 'comment_post_ID' );
		}

		$post_id = (int) $request->get_param( 'post_id' );

		return ( 0 === $post_id ) ? (int) $request->get_param( 'comment_post_ID' ) : $post_id;
	}

	/**
	 * Resolve the exact occurrence a request names, without entering it.
	 *
	 * Step 1 of the resolve-authorize-use sequence
	 * (`Event\Recurrence\Occurrence_Identity`). The identity that comes back
	 * names the post that actually owns the occurrence row, which a forward
	 * split can make different from the post the caller named, so authorization
	 * and mutation both work from the owner rather than from the request.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 *
	 * @return Occurrence_Identity|null The identity, or null when the request names no resolvable occurrence.
	 */
	private function requested_occurrence( WP_REST_Request $request ): ?Occurrence_Identity {
		$recurrence_id = (string) $request->get_param( self::RECURRENCE_ID_PARAM );

		if ( '' === $recurrence_id ) {
			return null;
		}

		return Occurrence_Identity::resolve( $this->request_post_id( $request ), $recurrence_id );
	}

	/**
	 * Enter the occurrence context a request names, for the rest of the dispatch.
	 *
	 * `Context::sync()` is hooked on `wp`, which a REST request never reaches:
	 * core's `rest_api_loaded()` runs on `parse_request` and ends in `die()`,
	 * so `WP::main()` never fires `wp`. Without this, every RSVP written
	 * through the REST layer is a series-wide RSVP however the visitor got
	 * there, and every read returns the union of the series.
	 *
	 * Returning early on an absent parameter is what keeps the series behavior
	 * byte-identical: nothing is resolved, no frame is pushed, and no occurrence
	 * machinery is touched.
	 *
	 * A failure to resolve is returned rather than swallowed. Continuing would
	 * serve the caller the **whole series** under a 200, which is precisely the
	 * outcome the caller narrowing to one date asked not to have.
	 *
	 * Every call is a push, and every caller that gets a `null` back owes a
	 * matching `unwind_occurrence_frame()` from a `finally`. The frame records
	 * the context that was standing when this dispatch entered, so a nested
	 * dispatch restores its caller's occurrence on the way out instead of
	 * clearing the process.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 *
	 * @return WP_Error|null An error when the named occurrence could not be entered, otherwise null.
	 */
	private function enter_occurrence_context( WP_REST_Request $request ): ?WP_Error {
		$recurrence_id = (string) $request->get_param( self::RECURRENCE_ID_PARAM );

		if ( '' === $recurrence_id ) {
			return null;
		}

		$previous = Context::get_instance()->current();

		if ( ! Context::get_instance()->set_for_series( $this->request_post_id( $request ), $recurrence_id ) ) {
			// `set_for_series()` clears context when it cannot resolve, so put
			// the caller's occurrence back before handing the error up: an
			// inner dispatch failing must not widen the outer route's scope.
			Context::get_instance()->restore( $previous );

			return $this->occurrence_not_found();
		}

		$this->occurrence_frames[] = array(
			'request'  => $request,
			'previous' => $previous,
		);

		return null;
	}

	/**
	 * Leave the occurrence context a route entered, on every way out of it.
	 *
	 * Called from the `finally` of each route that enters, which is the whole
	 * point: teardown used to hang off `rest_request_after_callbacks`, and that
	 * filter is applied only once the callback has **returned a value**. An
	 * exception thrown anywhere in a route body never reaches it, and these
	 * bodies run arbitrary third-party code: `rsvp_status_html()` renders
	 * whatever blocks the site has, and every one of these routes queries
	 * comments through filterable clauses. A throw there left the frame on the
	 * stack and the process still scoped to that occurrence, so anything later
	 * in the same request silently read and wrote one visitor's date. A
	 * `finally` cannot be skipped that way, and it also runs strictly earlier,
	 * at the end of the callback rather than after it.
	 *
	 * REST dispatch nests: `rsvp_status_html()` renders arbitrary blocks, any of
	 * which may call `rest_do_request()` for another occurrence of the same
	 * series. Two rules keep that safe, and both are load-bearing:
	 *
	 * - Only the **innermost** frame may be torn down. An inner dispatch that
	 *   named no occurrence pushed no frame, and its own `finally` still calls
	 *   this; keying on request identity is what stops it unwinding the outer
	 *   route's frame instead of its own.
	 * - Teardown **restores** the frame's previous occurrence rather than
	 *   clearing. The context belongs to whatever is still running, not to the
	 *   process, so clearing unconditionally left the outer route reading and
	 *   writing series-wide for the remainder of its callback with no error
	 *   anywhere.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request The request whose frame should be unwound.
	 *
	 * @return void
	 */
	private function unwind_occurrence_frame( WP_REST_Request $request ): void {
		$innermost = end( $this->occurrence_frames );

		if ( false === $innermost || $request !== $innermost['request'] ) {
			return;
		}

		array_pop( $this->occurrence_frames );

		Context::get_instance()->restore( $innermost['previous'] );
	}

	/**
	 * Read the one RSVP token parameter this route recognizes.
	 *
	 * The token used to arrive under two names: the route registered
	 * `rsvp_token`, which is what the browser sends and what `update_rsvp()`
	 * reads, while the permission callback read `gatherpress_rsvp_token`, the
	 * name the magic link carries in the *page* URL. A caller could therefore
	 * satisfy authorization with one value and be identified by another, and
	 * nothing compared the two.
	 *
	 * `rsvp_token` is now the single recognized name. A request carrying the
	 * page-URL name as well is refused outright rather than having one of the
	 * two silently win, because there is no legitimate client that sends both
	 * and a caller sending both is asking two different questions.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 *
	 * @return string|null The token string, or null when there is none or the request names two.
	 */
	private function request_token_string( WP_REST_Request $request ): ?string {
		$canonical = (string) $request->get_param( 'rsvp_token' );
		$legacy    = (string) $request->get_param( Token::NAME );

		if ( '' !== $legacy ) {
			return null;
		}

		return '' === $canonical ? null : $canonical;
	}

	/**
	 * Permission callback gating an RSVP write.
	 *
	 * Two ways in, and the occurrence rule is the same for both: the identity
	 * the caller's credential carries must be the exact identity the request
	 * names.
	 *
	 * A magic-link token is a credential for **one stored RSVP**, and that RSVP
	 * belongs to one occurrence. Authorizing on the token's event post alone
	 * let the holder of a token for September 17th write the token holder's
	 * email into September 24th's roster: the event matched, so permission
	 * passed, and the callback then entered whichever occurrence the request
	 * named. Comparing whole identities closes that, in both directions.
	 * `Occurrence_Identity::matches()` treats absence as a value, so a
	 * series-wide token cannot act on an occurrence and an occurrence token
	 * cannot act series-wide.
	 *
	 * The occurrence is resolved here only once a token has already
	 * authenticated. Resolution reads storage, and doing it for an
	 * unauthenticated caller would answer "is this a real occurrence?" before
	 * anything had established the caller's right to ask.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 *
	 * @return bool|WP_Error True when the caller may write this RSVP, a 403 when a
	 *                       presented token does not carry the requested occurrence, or
	 *                       the shared not-found refusal when the occurrence's owner
	 *                       refuses a logged-in caller.
	 */
	public function can_update_rsvp( WP_REST_Request $request ): bool|WP_Error {
		$post_id    = (int) $request->get_param( 'post_id' );
		$rsvp_token = Token::from_token_string( $this->request_token_string( $request ) );
		$token_post = $rsvp_token ? $rsvp_token->get_post() : null;
		$comment    = $rsvp_token ? $rsvp_token->get_comment() : null;

		if ( $token_post instanceof WP_Post && $token_post->ID === $post_id && null !== $comment ) {
			$requested = $this->requested_occurrence( $request );
			$allowed   = Occurrence_Identity::matches(
				Occurrence_Identity::for_comment( (int) $comment->comment_ID ),
				$requested
			) && $this->can_access_occurrence_owner( $requested, $post_id );

			// One message and one status for every refusal, whether the
			// requested occurrence is real, belongs to a sibling, or does not
			// exist. A refusal that varied would tell a link holder which of
			// the series' other dates exist.
			return $allowed ? true : new WP_Error(
				'gatherpress_rsvp_token_scope',
				__( 'This RSVP link does not authorize the requested occurrence.', 'gatherpress' ),
				array( 'status' => 403 )
			);
		}

		// Otherwise the caller must be logged in and able to read the event.
		// `can_read_event_rsvps()` carries the whole rule, ordered so storage
		// is only read for a caller the named-post check has already admitted,
		// and its owner refusal is returned as-is so the refusal stays
		// indistinguishable from a fabricated identifier's.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return $this->can_read_event_rsvps( $request );
	}

	/**
	 * Authorize the post that actually owns the occurrence being read or written.
	 *
	 * The named post and the owner are the same post for every series that has
	 * never been split, so this is a no-op today. It exists because they stop
	 * being the same post the moment the forward split moves an occurrence
	 * onto a sibling: authorization would then have been granted against the
	 * fragment the caller named while the read or write landed on a fragment
	 * nobody checked. Capability on one fragment must never authorize access
	 * to an unauthorized sibling, and the rule is the same for a roster read
	 * as for a mutation, because the read is what discloses the roster.
	 *
	 * @since 0.36.0
	 *
	 * @param Occurrence_Identity|null $identity      The resolved occurrence, if any.
	 * @param int                      $named_post_id The post the request named.
	 *
	 * @return bool True when the caller may act against the owning post.
	 */
	private function can_access_occurrence_owner( ?Occurrence_Identity $identity, int $named_post_id ): bool {
		if ( null === $identity || $identity->owner_post_id === $named_post_id ) {
			return true;
		}

		return Event::can_read_rsvps( $identity->owner_post_id );
	}

	/**
	 * Build the one refusal every unusable occurrence receives.
	 *
	 * The single source of that refusal, shared by
	 * `enter_occurrence_context()`, `can_read_event_rsvps()` and
	 * `handle_rsvp_form_submission()`, so a caller cannot tell an occurrence
	 * that does not exist from one whose owner refused them. Two refusals that
	 * drifted apart in status, code or message would let a caller authorized
	 * on one post of a series enumerate which occurrences of an unreadable
	 * sibling exist, one candidate at a time.
	 *
	 * @since 0.36.0
	 *
	 * @return WP_Error The not-found refusal, identical for every caller.
	 */
	private function occurrence_not_found(): WP_Error {
		return new WP_Error(
			'gatherpress_occurrence_not_found',
			__( 'The requested occurrence no longer exists.', 'gatherpress' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Permission callback gating read access to an event's RSVP roster.
	 *
	 * Mirrors the visibility of the event page itself: a published roster is
	 * public (subject to any password gate), while other statuses require read
	 * access to the specific event. Editors keep access in every state.
	 *
	 * The roster callbacks read from the post that owns the requested
	 * occurrence, which a forward split can make a different post from the one
	 * the request named, so the owner is authorized here too. The named-post
	 * check runs first and alone for a refused caller: the owner re-check
	 * resolves the occurrence from storage, and resolving for a caller the
	 * named post refuses would answer "is this occurrence real?" for a caller
	 * with no right to ask. An authorized caller whose resolved owner refuses
	 * them gets the exact refusal a fabricated identifier gets, for the reason
	 * given on `occurrence_not_found()`.
	 *
	 * @since 0.35.1
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return bool|WP_Error True when the caller may read the event's RSVP responses, false
	 *                       when the named post refuses them, or the shared not-found refusal
	 *                       when the occurrence's owner does.
	 */
	public function can_read_event_rsvps( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! Event::can_read_rsvps( $post_id ) ) {
			return false;
		}

		if ( $this->can_access_occurrence_owner( $this->requested_occurrence( $request ), $post_id ) ) {
			return true;
		}

		return $this->occurrence_not_found();
	}

	/**
	 * Reduce an RSVP responses payload to per-status counts.
	 *
	 * The public RSVP form endpoint returns totals so the block can refresh
	 * its counts, but the submitter may be anonymous and has no claim on the
	 * attendee records, so the identifying `records` arrays are dropped.
	 *
	 * @since 0.35.1
	 *
	 * @param array<string, array{records: array<int, array<string, mixed>>, count: int}> $responses Full payload from
	 *                                                                                               Rsvp::responses().
	 *
	 * @return array<string, array{count: int}> The same status keys, each carrying only its count.
	 */
	private function rsvp_response_counts( array $responses ): array {
		return array_map(
			static fn( array $group ) => array( 'count' => $group['count'] ),
			$responses
		);
	}

	/**
	 * Send an event email notification to members.
	 *
	 * This method allows sending an email notification about a specific event to members.
	 * It checks the user's capability to edit posts before initiating the email sending process.
	 * If the user doesn't have the required capability, the method returns a response with 'success' set to false.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response The response indicating the success of the email scheduling process.
	 */
	public function email( WP_REST_Request $request ): WP_REST_Response {
		$params   = $request->get_params();
		$post_id  = intval( $params['post_id'] );
		$message  = $params['message'] ?? '';
		$subject  = $params['subject'] ?? '';
		$send     = $params['send'];
		$success  = wp_schedule_single_event(
			time(),
			'gatherpress_send_emails',
			array( $post_id, $send, $message, $subject )
		);
		$response = array(
			'success' => $success,
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Hooked method to trigger the sending of related emails.
	 *
	 * This method hooks into a WordPress action, triggering the `send_emails` method
	 * to send emails to selected members. It doesn't return any value,
	 * as it's intended to be called by an action hook.
	 *
	 * @since 0.34.0
	 * @since 0.36.0 Added `$subject` parameter for #827.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $send    Members to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @param string $subject Optional subject line. Defaults to the existing `📅 {title}` template when empty.
	 * @phpstan-param SendOptions $send
	 *
	 * @return void
	 */
	public function handle_email_send_action( int $post_id, array $send, string $message, string $subject = '' ): void {
		$this->send_emails( $post_id, $send, $message, $subject );
	}

	/**
	 * Send emails to selected members.
	 *
	 * This method is responsible for sending emails to specific members. It checks if the given
	 * `$post_id` corresponds to a specific post type, retrieves the list of members to email, and sends the email with
	 * the appropriate subject, body, and headers.
	 *
	 * @since 0.34.0
	 * @since 0.36.0 Added `$subject` parameter for #827.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $send    Members to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @param string $subject Optional subject line. Defaults to the existing `📅 {title}` template when empty.
	 * @phpstan-param SendOptions $send
	 *
	 * @return bool True if emails were successfully sent, false otherwise.
	 */
	public function send_emails( int $post_id, array $send, string $message, string $subject = '' ): bool {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}

		// Keep the currently logged-in user so per-recipient locale / user
		// switches inside the loop can restore back to it.
		$current_user = wp_get_current_user();
		$recipients   = $this->get_recipients( $send, $post_id );

		foreach ( $recipients as $recipient ) {
			$this->send_event_email_to_recipient( $recipient, $post_id, $message, $current_user, $subject );
		}

		return true;
	}

	/**
	 * Send the per-event update email to a single recipient.
	 *
	 * Extracted from `send_emails()` so the outer loop body stays shallow
	 * enough for SonarCloud's cognitive-complexity gate. Honors the
	 * recipient's opt-in (user meta for WP users, comment meta for
	 * non-user RSVPs) and skips silently when no email is on file.
	 * Restores the editor's user / locale before returning.
	 *
	 * @since 0.34.0
	 * @since 0.36.0 Added `$subject` parameter for #827.
	 *
	 * @param array   $recipient    Recipient row from `get_recipients()`.
	 * @param int     $post_id      Event post ID.
	 * @param string  $message      Optional editor-supplied message body.
	 * @param WP_User $current_user Originating editor (restored after locale/user switch).
	 * @param string  $subject      Optional subject line. Empty falls back to the default template
	 *                              and is then filtered via `gatherpress_email_subject`.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return void
	 */
	protected function send_event_email_to_recipient(
		array $recipient,
		int $post_id,
		string $message,
		WP_User $current_user,
		string $subject = ''
	): void {
		// Check opt-in preference based on recipient type.
		if ( $recipient['is_user'] ) {
			if ( ! User::get_instance()->has_event_updates_opt_in( $recipient['user_id'] ) ) {
				return;
			}
		} elseif (
			'0' === get_comment_meta(
				$recipient['comment_id'],
				'gatherpress_event_updates_opt_in',
				true
			)
		) {
			return;
		}

		if ( ! $recipient['email'] ) {
			return;
		}

		$switched_locale = false;

		// Set the current user context for templating.
		if ( $recipient['is_user'] ) {
			$switched_locale = switch_to_user_locale( $recipient['user_id'] );
			// Set the current user to the actual member to mail to,
			// to make sure the GatherPress filters for date- and time- format, as well as the users timezone,
			// are recognized by the functions inside render_template().
			wp_set_current_user( $recipient['user_id'] );
		}

		if ( '' === $subject ) {
			$subject = sprintf(
				// translators: %s: event title.
				_x( '📅 %s', 'Email notification subject with event title', 'gatherpress' ),
				get_the_title( $post_id )
			);
		}

		/**
		 * Filters the event update email subject.
		 *
		 * @since 0.36.0
		 *
		 * @param string $subject Email subject line.
		 * @param int    $post_id Event post ID.
		 */
		$subject = apply_filters( 'gatherpress_email_subject', $subject, $post_id );
		$body    = Utility::render_template(
			sprintf( '%s/includes/templates/admin/emails/event-email.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_id' => $post_id,
				'message'  => $message,
			),
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$subject = stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

		// Reset the current user to the editor sending the email.
		wp_set_current_user( $current_user->ID );

		wp_mail( $recipient['email'], $subject, $body, $headers );

		// Cleanup branch only fires when `switch_to_user_locale()` actually
		// switched, which requires a non-stub `WP_Locale_Switcher` and is not
		// reachable from the test runner.
		if ( $switched_locale ) { // @codeCoverageIgnore
			restore_previous_locale(); // @codeCoverageIgnore
		}
	}

	/**
	 * Get the list of recipients to send event-related emails to.
	 *
	 * This method retrieves the list of recipients to whom event-related emails should be sent
	 * based on the given `$send` parameter and the specified event `$post_id`.
	 * It checks the `$send` array for specific email recipient categories,
	 * such as 'all,' 'attending,' 'waiting_list,' and 'not_attending,' and compiles a unified list of recipients
	 * that includes both WordPress users and non-user RSVPs with their email addresses and metadata.
	 *
	 * @since 0.34.0
	 *
	 * @param array $send    An array specifying who to send emails to.
	 * @param int   $post_id The Event Post ID.
	 * @phpstan-param SendOptions $send
	 *
	 * @return array<int, Recipient> An array containing unified recipient data for both users and non-users.
	 */
	public function get_recipients( array $send, int $post_id ): array {
		$recipients    = array();
		$all_responses = ( new Rsvp( $post_id ) )->responses();

		// Handle 'all' members (WordPress users only) — array_map keeps the
		// per-user shape declarative.
		if ( ! empty( $send['all'] ) ) {
			$recipients = array_map(
				static function ( $user ): array {
					return array(
						'is_user'    => true,
						'user_id'    => $user->ID,
						'comment_id' => 0,
						'email'      => $user->user_email,
						'name'       => $user->display_name,
					);
				},
				get_users()
			);
		}

		// Collect comment IDs for the requested RSVP statuses — `array_column`
		// flattens each status's records to its commentId list in one pass,
		// avoiding the inner foreach.
		$comment_ids = array();
		foreach ( array( 'attending', 'waiting_list', 'not_attending' ) as $status ) {
			if ( ! empty( $send[ $status ] ) ) {
				$comment_ids = array_merge(
					$comment_ids,
					array_column( $all_responses[ $status ]['records'], 'comment_id' )
				);
			}
		}

		if ( empty( $comment_ids ) ) {
			return $recipients;
		}

		// Get full comment data for the RSVPs and build recipient rows.
		$comments = Rsvp_Query::get_instance()->get_rsvps(
			array(
				'post_id'     => $post_id,
				'status'      => 'approve',
				'comment__in' => $comment_ids,
			)
		);

		foreach ( $comments as $comment ) {
			// get_rsvps() is typed loosely enough to return counts, so only comment rows
			// are turned into recipients.
			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}

			$recipient = $this->build_comment_recipient( $comment );

			if ( null !== $recipient ) {
				$recipients[] = $recipient;
			}
		}

		return $recipients;
	}

	/**
	 * Build a single recipient row from an approved RSVP comment, resolving
	 * the user's email/display name when the comment is tied to a WordPress
	 * user. Returns null when no email can be determined so the caller can
	 * skip the row.
	 *
	 * Extracted from `get_recipients()` so the outer dispatch stays under
	 * SonarCloud's cognitive-complexity threshold.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Comment $comment RSVP comment row from `Rsvp_Query::get_rsvps()`.
	 *
	 * @return Recipient|null Recipient row, or null when no email is on file.
	 */
	protected function build_comment_recipient( $comment ): ?array {
		$user_id = intval( $comment->user_id );
		$email   = $comment->comment_author_email;
		$name    = $comment->comment_author;

		if ( $user_id ) {
			$user = get_userdata( $user_id );

			if ( $user ) {
				$email = $user->user_email;
				$name  = $user->display_name;
			}
		}

		if ( empty( $email ) ) {
			return null;
		}

		return array(
			'is_user'    => (bool) $user_id,
			'user_id'    => $user_id,
			'comment_id' => (int) $comment->comment_ID,
			'email'      => $email,
			'name'       => $name,
		);
	}

	/**
	 * Update the RSVP status for a user to an event.
	 *
	 * This method handles the update of the RSVP status for a user to an event, including handling guest count.
	 * It checks the user's permissions and the event's status to ensure a valid update. If the update is successful,
	 * it returns relevant information, including the updated status, guest count, and responses.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error The response data, or a 404 when the named occurrence no longer resolves.
	 */
	public function update_rsvp( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Prevent caching of RSVP updates.
		nocache_headers();

		// Scope every read and write below to the occurrence the visitor is
		// looking at, when the request names one.
		$occurrence_error = $this->enter_occurrence_context( $request );

		if ( null !== $occurrence_error ) {
			return $occurrence_error;
		}

		try {
			$params          = $request->get_params();
			$success         = false;
			$current_user_id = get_current_user_id();
			$blog_id         = get_current_blog_id();
			$user_id         = isset( $params['user_id'] ) ? intval( $params['user_id'] ) : $current_user_id;
			$status          = sanitize_key( $params['status'] );
			$guests          = intval( $params['guests'] ?? 0 );
			$anonymous       = intval( $params['anonymous'] ?? 0 );
			$unparsed_token  = (string) $this->request_token_string( $request );

			// Step 3 of resolve-authorize-use: the mutation runs against the post
			// that owns the authorized occurrence, not against whichever post the
			// request named. The two are the same post until a forward split moves
			// an occurrence onto a sibling, and after one they are not. Writing to
			// the named post there would store the response under an owner no
			// reader of that occurrence queries by.
			$identity = $this->requested_occurrence( $request );
			$post_id  = ( null === $identity ) ? intval( $params['post_id'] ) : $identity->owner_post_id;
			$event    = new Event( $post_id );

			// If managing user is adding someone to an event.
			$is_managing_other = false;
			if (
				$current_user_id &&
				$user_id &&
				$current_user_id !== $user_id
			) {
				// Per-event check: only users who can edit *this* event may
				// RSVP someone else into it. The previous flat `edit_posts`
				// check would have let any Author manage attendees on any
				// event, including ones they don't own.
				if ( current_user_can( Event::EDIT_CAPABILITY, $post_id ) ) {
					$is_managing_other = true;
				} else {
					$user_id = 0;
				}
			} else {
				$user_id = $current_user_id;
			}

			// Auto-join the current blog when the RSVP target is not yet a member.
			// A user RSVPing *themselves* joins as a subscriber (the open-RSVP
			// across-network flow). Enrolling *another* user is a higher-privilege
			// action: `edit_post` lets an editor manage attendees, but adding users
			// to a site is gated by `promote_users` in WordPress, so require that
			// capability here and confirm the target is a real user before creating
			// any membership.
			if (
				intval( $user_id )
				&& ! is_user_member_of_blog( $user_id )
				&& ( ! $is_managing_other || ( current_user_can( 'promote_users' ) && get_userdata( $user_id ) ) )
			) {
				add_user_to_blog( $blog_id, $user_id, 'subscriber' );
			}

			$user_identifier = $user_id;

			if ( ! empty( $unparsed_token ) ) {
				$rsvp_token = Token::from_token_string( $unparsed_token );

				if ( $rsvp_token ) {
					$user_identifier = $rsvp_token->get_email();
				}
			}

			if (
				$user_identifier &&
				( is_user_member_of_blog( $user_identifier ) || is_email( $user_identifier ) ) &&
				! $event->has_event_past()
			) {
				if ( 'attending' !== $status ) {
					$guests = 0;
				}

				$user_record = ( new Rsvp( $post_id ) )->save( $user_identifier, $status, $anonymous, $guests );
				$status      = $user_record['status'];
				$guests      = $user_record['guests'];

				if ( in_array( $status, Status::values(), true ) ) {
					$success = true;
				}
			}

			$response = array(
				'event_id'    => $post_id,
				'success'     => $success,
				'status'      => $status,
				'guests'      => $guests,
				'anonymous'   => $anonymous,
				'responses'   => ( new Rsvp( $post_id ) )->responses(),
				'online_link' => $event->maybe_get_online_event_link(),
			);

			return new WP_REST_Response( $response );
		} finally {
			// Every exit from the body unwinds, a throw included. See
			// `unwind_occurrence_frame()` for why a `finally` rather than a
			// filter on the dispatch that follows the callback.
			$this->unwind_occurrence_frame( $request );
		}
	}

	/**
	 * Handles rendering RSVP block HTML via a REST API endpoint.
	 *
	 * This method dynamically generates HTML markup for RSVP blocks based on the
	 * provided block data and the responses for a given post ID. It processes the
	 * RSVP responses and renders the corresponding content using the block template.
	 * Each response is wrapped in its own container with data attributes to facilitate
	 * interactivity and styling.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_REST_Request $request The REST API request object containing parameters:
	 *                                 - post_id (int): The ID of the post associated with the RSVP.
	 *                                 - block_data (string): JSON-encoded block data used to render the RSVP content.
	 *                                 - block_signature (string): Signature emitted alongside block_data.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error A 404 when the named occurrence no longer resolves, otherwise
	 *                                a response containing:
	 *                          - success (bool): Whether the content was successfully generated.
	 *                          - content (string): The dynamically rendered HTML markup for the RSVP responses.
	 */
	public function rsvp_status_html( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Prevent caching for logged-in users or users with valid RSVP tokens.
		$unparsed_token = $request->get_param( Token::NAME );
		$rsvp_token     = Token::from_token_string( $unparsed_token );

		if ( is_user_logged_in() || $rsvp_token ) {
			nocache_headers();
		}

		// Scope the rendered roster to the occurrence the visitor is looking at.
		$occurrence_error = $this->enter_occurrence_context( $request );

		if ( null !== $occurrence_error ) {
			return $occurrence_error;
		}

		try {
			$rsvp_template = Rsvp_Template::get_instance();
			$params        = $request->get_params();
			$identity      = $this->requested_occurrence( $request );
			// The roster is read from the post that owns the occurrence, which a
			// forward split can make different from the post the request named.
			// Reading the named post there would apply the owner's occurrence term
			// to the wrong post and return an empty roster under a 200.
			$post_id    = ( null === $identity ) ? intval( $params['post_id'] ) : $identity->owner_post_id;
			$status     = $params['status'];
			$block_data = $params['block_data'];
			$block_data = json_decode( $block_data, true );
			$rsvp       = new Rsvp( $post_id );
			$responses  = $rsvp->responses();
			$content    = '';
			// @todo set this up...
			$args = array(
				'limit_enabled' => (bool) $params['limit_enabled'],
				'limit'         => (int) $params['limit'],
			);

			if ( ! empty( $responses[ $status ] ) ) {
				foreach ( $responses[ $status ]['records'] as $key => $record ) {
					$args['index'] = $key;
					$content      .= $rsvp_template->get_block_content( $block_data, $record['comment_id'], $args );
				}
			}

			$success = true;

			$response = array(
				'success'   => $success,
				'content'   => $content,
				'responses' => $responses,
			);

			return new WP_REST_Response( $response );
		} finally {
			// Every exit from the body unwinds, a throw included. See
			// `unwind_occurrence_frame()` for why a `finally` rather than a
			// filter on the dispatch that follows the callback.
			$this->unwind_occurrence_frame( $request );
		}
	}

	/**
	 * Handle RSVP form submission via Ajax.
	 *
	 * This method processes RSVP form submissions received via Ajax,
	 * using the centralized Rsvp\Form class for consistency.
	 *
	 * @since 0.34.0
	 *
	 * Just over PHPMD's NPath threshold (9,232 against 9,217). The branches are
	 * the submission's own gates, and their order is load bearing: the owner's
	 * viewability is checked before the occurrence is resolved and before the
	 * context is entered, which is what stops a submission naming a viewable
	 * sibling from landing on an unviewable owner. A refactor to shed paths
	 * could reorder that silently, so the count is suppressed rather than
	 * chased. Restructuring it remains open for the maintainers.
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error Success or failure, or a 404 when the named occurrence no longer resolves.
	 */
	public function handle_rsvp_form_submission( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Prevent caching of RSVP form submission responses.
		nocache_headers();

		// Viewability is settled before any occurrence is resolved, and that
		// order is the fix rather than a tidy-up. This route's
		// `permission_callback` is `__return_true`, so it is the only place an
		// unauthenticated caller reaches occurrence resolution at all. Resolving
		// first meant a draft or private event answered "that occurrence exists"
		// with a 404 naming the occurrence and "it does not" with a different
		// status, which let a visitor who cannot read the event enumerate its
		// schedule one `Ymd\THis` candidate at a time. Bailing here returns the
		// identical `Event not found.` 404 for a real occurrence and a
		// fabricated one, having run identical work to produce it.
		$post_id = intval( $request->get_param( 'comment_post_ID' ) );

		if ( ! Event::is_viewable( $post_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Event not found.', 'gatherpress' ),
				),
				404
			);
		}

		// Steps 1 and 2 of resolve-authorize-use, on the one route whose
		// `permission_callback` is `__return_true`: the response is written
		// against the post that owns the occurrence, so that owner is the post
		// that must pass the same viewability gate the named post just did.
		// The two are identical on every unsplit series, and deliberately not
		// identical after a forward split has moved the occurrence onto a
		// sibling; without this, naming a viewable sibling wrote an RSVP onto
		// a private owner nobody authorized. The refusal is the exact refusal
		// a fabricated identifier gets, for the reason given on
		// `occurrence_not_found()`.
		$identity = $this->requested_occurrence( $request );

		if (
			null !== $identity
			&& $identity->owner_post_id !== $post_id
			&& ! Event::is_viewable( $identity->owner_post_id )
		) {
			return $this->occurrence_not_found();
		}

		// Scope the duplicate check, the written response and the returned
		// counts to the occurrence the visitor is looking at.
		$occurrence_error = $this->enter_occurrence_context( $request );

		if ( null !== $occurrence_error ) {
			return $occurrence_error;
		}

		try {
			$params = $request->get_params();

			// Step 3: the write itself runs against the authorized owner.
			$post_id = ( null === $identity ) ? $post_id : $identity->owner_post_id;

			// Prepare data for the RSVP processor.
			$data = array(
				'post_id'                          => $post_id,
				'author'                           => $params['author'] ?? '',
				'email'                            => $params['email'] ?? '',
				'gatherpress_event_updates_opt_in' => $request->get_param( 'gatherpress_event_updates_opt_in' ),
				'gatherpress_rsvp_guests'          => $request->get_param( 'gatherpress_rsvp_form_guests' ),
				'gatherpress_rsvp_anonymous'       => $request->get_param( 'gatherpress_rsvp_form_anonymous' ),
				'gatherpress_form_schema_id'       => $request->get_param( 'gatherpress_form_schema_id' ),
			);

			// Add custom fields to data.
			foreach ( $params as $key => $value ) {
				if ( str_starts_with( $key, 'gatherpress_custom_' ) ) {
					$data[ $key ] = $value;
				}
			}

			// Also include custom fields defined in form schema.
			$form_schema_id = $data['gatherpress_form_schema_id'] ?? '';

			if ( ! empty( $form_schema_id ) ) {
				$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

				if ( is_array( $schemas ) && isset( $schemas[ $form_schema_id ]['fields'] ) ) {
					$fields = $schemas[ $form_schema_id ]['fields'];
					foreach ( array_keys( $fields ) as $field_name ) {
						if ( isset( $params[ $field_name ] ) ) {
							$data[ $field_name ] = $params[ $field_name ];
						}
					}
				}
			}

			// Pre-flight: bail with a structured error before processing if the
			// event is not viewable, open RSVP is disabled or the event has already passed.
			$event = new Event( $data['post_id'] );
			$rsvp  = new Rsvp( $data['post_id'] );
			$bail  = null;

			if ( ! $rsvp->is_enabled() ) {
				$bail = array( __( 'RSVP is disabled for this event.', 'gatherpress' ), 403 );
			} elseif ( ! $rsvp->allows_open_rsvp() ) {
				$bail = array( __( 'Open RSVP is disabled for this event.', 'gatherpress' ), 403 );
			} elseif ( $event->has_event_past() ) {
				$bail = array( __( 'Registration for this event is now closed.', 'gatherpress' ), 400 );
			}

			if ( null !== $bail ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $bail[0],
					),
					$bail[1]
				);
			}

			// Process the RSVP using the centralized processor.
			$result = Form::get_instance()->process_rsvp( $data );

			// One trailing return covers both the success and error shapes: the
			// success body carries comment_id + responses, the error body just
			// the message and the upstream error_code.
			if ( $result['success'] ) {
				$response = array(
					'success'    => true,
					'message'    => $result['message'],
					'comment_id' => $result['comment_id'],
					'responses'  => $this->rsvp_response_counts( ( new Rsvp( $post_id ) )->responses() ),
				);
				$status   = 200;
			} else {
				$response = array(
					'success' => false,
					'message' => $result['message'],
				);
				$status   = $result['error_code'] ?? 500;
			}

			return new WP_REST_Response( $response, $status );
		} finally {
			// Every exit from the body unwinds, a throw included. See
			// `unwind_occurrence_frame()` for why a `finally` rather than a
			// filter on the dispatch that follows the callback.
			$this->unwind_occurrence_frame( $request );
		}
	}

	/**
	 * Handle RSVP responses REST endpoint request.
	 *
	 * Retrieves RSVP response data for a given event post ID. Validates that the post
	 * is an event type before returning response data.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_REST_Request $request REST API request object containing post_id parameter.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error Success status and RSVP data, or a 404 when the named
	 *                                occurrence no longer resolves.
	 */
	public function rsvp_responses( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Prevent caching for logged-in users or users with valid RSVP tokens.
		$unparsed_token = $request->get_param( Token::NAME );
		$rsvp_token     = Token::from_token_string( $unparsed_token );

		if ( is_user_logged_in() || $rsvp_token ) {
			nocache_headers();
		}

		// Scope the returned roster to the occurrence the caller named.
		$occurrence_error = $this->enter_occurrence_context( $request );

		if ( null !== $occurrence_error ) {
			return $occurrence_error;
		}

		try {
			$params   = $request->get_params();
			$identity = $this->requested_occurrence( $request );
			// Read from the occurrence's owning post, for the reason given on
			// `rsvp_status_html()`.
			$post_id   = ( null === $identity ) ? intval( $params['post_id'] ) : $identity->owner_post_id;
			$success   = false;
			$responses = array();

			if ( Event::POST_TYPE === get_post_type( $post_id ) ) {
				$success   = true;
				$rsvp      = new Rsvp( $post_id );
				$responses = $rsvp->responses();
			}

			$response = array(
				'success' => $success,
				'data'    => $responses,
			);

			return new WP_REST_Response( $response );
		} finally {
			// Every exit from the body unwinds, a throw included. See
			// `unwind_occurrence_frame()` for why a `finally` rather than a
			// filter on the dispatch that follows the callback.
			$this->unwind_occurrence_frame( $request );
		}
	}


	/**
	 * Prepare event data for the response.
	 *
	 * This method prepares and enhances the event data for the response object.
	 * It retrieves additional meta information, such as the online event link, based on specific conditions.
	 * The enhanced data is then added to the response.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_REST_Response $response The response object containing event data.
	 *
	 * @return WP_REST_Response The response object with enhanced event data.
	 */
	public function prepare_event_data( WP_REST_Response $response ): WP_REST_Response {
		// The response data shape depends on what the controller included: a
		// `_fields=` request that drops `id`, or another plugin filtering the
		// response, can leave it absent. Bail rather than emit an undefined-key
		// notice and construct Event( 0 ), which would silently do nothing.
		$post_id = $response->data['id'] ?? 0;

		if ( ! $post_id ) {
			return $response;
		}

		$event = new Event( $post_id );

		// Retrieve the online event link only if:
		// - The user is attending the event.
		// - The event is in the future.
		// - The code is not in an admin context.
		$response->data['meta']['online_event_link'] = $event->maybe_get_online_event_link();

		return $response;
	}
}
