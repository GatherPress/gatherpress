<?php
/**
 * Experiments settings page for GatherPress.
 *
 * Registers a "GatherPress Experiments" sub-page inside the GatherPress
 * settings area and renders a card list of experiments sourced from the
 * static data file at includes/data/experiments.php.
 *
 * For each experiment the page fetches the 👍 reaction count from the
 * GitHub REST API (cached in a transient for 1 hour) and renders links
 * to the GitHub Discussion and to the WordPress Playground blueprint.
 *
 * @package GatherPress\Core\Settings
 * @since 0.34.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Experiments.
 *
 * @since 0.34.0
 */
class Experiments extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Transient key prefix used to cache vote counts per discussion URL.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'gatherpress_exp_votes_';

	/**
	 * How long (in seconds) to cache vote counts fetched from GitHub.
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Get the slug for the experiments settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'experiments';
	}

	/**
	 * Get the name for the experiments settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	protected function get_name(): string {
		return __( 'Experiments', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the experiments settings page.
	 *
	 * Sits just before Credits (PHP_INT_MAX) and after Tools (PHP_INT_MAX - 1).
	 *
	 * @since 0.34.0
	 *
	 * @return int
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX - 2;
	}

	/**
	 * Enqueue inline styles only on our settings sub-page.
	 *
	 * @since 0.34.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// GatherPress settings pages share a single hook suffix.
		if ( ! str_contains( $hook, 'gatherpress' ) ) {
			return;
		}

		// Only inject when our sub-page is active.
		$page = Utility::get_http_input( INPUT_GET, 'page' );
		if ( Utility::unprefix_key( (string) $page ) !== $this->get_slug() ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->inline_styles() );
	}

	/**
	 * Render the custom experiments section instead of the default settings form.
	 *
	 * @since 0.34.0
	 *
	 * @param string $page The current settings page slug.
	 *
	 * @return void
	 */
	public function settings_section( string $page ): void {
		if ( Utility::unprefix_key( $page ) !== $this->get_slug() ) {
			return;
		}

		remove_action(
			'gatherpress_settings_section',
			array( Settings::get_instance(), 'render_settings_form' )
		);

		$experiments = $this->get_experiments();

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/experiments.php', GATHERPRESS_CORE_PATH ),
			array( 'experiments' => $experiments ),
			true
		);
	}

	/**
	 * Load the static experiments list and enrich each item with live discussion
	 * stats (votes, comments) and a resolved Playground launch URL.
	 *
	 * @since 0.34.0
	 *
	 * @return array
	 */
	public function get_experiments(): array {
		$raw = require GATHERPRESS_CORE_PATH . '/includes/data/experiments.php';

		if ( ! is_array( $raw ) ) {
			return array();
		}

		foreach ( $raw as &$experiment ) {
			$stats                        = $this->get_discussion_stats( $experiment['discussion'] ?? '' );
			$experiment['reactions']      = $stats['reactions'];
			$experiment['comments']       = $stats['comments'];
			$experiment['playground_url'] = $this->build_playground_url( $experiment['blueprint'] ?? '' );
		}
		unset( $experiment );

		return $raw;
	}

	/**
	 * Build the WordPress Playground launch URL from an external blueprint.json URL.
	 *
	 * @since 0.34.0
	 *
	 * @param string $blueprint_url Raw URL to an external blueprint.json file.
	 *
	 * @return string Full Playground launch URL, or empty string when no blueprint URL is given.
	 */
	public function build_playground_url( string $blueprint_url ): string {
		if ( empty( $blueprint_url ) ) {
			return '';
		}

		return add_query_arg(
			'blueprint-url',
			rawurlencode( $blueprint_url ),
			'https://playground.wordpress.net/'
		);
	}

	/**
	 * Reaction types tracked by GitHub, mapped to their display emoji.
	 *
	 * @since 0.34.0
	 * @var array<string,string>
	 */
	const REACTION_EMOJI = array(
		'+1'       => '&#x1F44D;',
		'-1'       => '&#x1F44E;',
		'laugh'    => '&#x1F604;',
		'hooray'   => '&#x1F389;',
		'confused' => '&#x1F615;',
		'heart'    => '&#x2764;&#xFE0F;',
		'rocket'   => '&#x1F680;',
		'eyes'     => '&#x1F440;',
	);

	/**
	 * Fetch per-type reaction counts and comment count for a GitHub Discussion URL.
	 *
	 * Results are cached in a transient for {@see self::CACHE_TTL} seconds.
	 * Returns an array with:
	 *   'reactions' => array<string,int>  only types with count > 0, keyed by GitHub type slug
	 *   'comments'  => int|null
	 *
	 * @since 0.34.0
	 *
	 * @param string $discussion_url Full URL of the GitHub Discussion.
	 *
	 * @return array{ reactions: array<string,int>, comments: int|null }
	 */
	public function get_discussion_stats( string $discussion_url ): array {
		$empty = array( 'reactions' => array(), 'comments' => null );

		if ( empty( $discussion_url ) ) {
			return $empty;
		}

		$transient_key = self::TRANSIENT_PREFIX . substr( md5( $discussion_url ), 0, 16 );
		$cached        = get_transient( $transient_key );

		if ( is_array( $cached ) && array_key_exists( 'reactions', $cached ) ) {
			return $cached;
		}

		$stats = $this->fetch_discussion_stats_from_github( $discussion_url );

		set_transient( $transient_key, $stats ?? $empty, self::CACHE_TTL );

		return $stats ?? $empty;
	}

	/**
	 * Call the GitHub REST API to fetch reaction and comment counts for a discussion.
	 *
	 * Uses GET /repos/{owner}/{repo}/discussions/{id} which returns both a
	 * `reactions` object (with per-type counts) and a top-level `comments`
	 * integer in a single request. Only non-zero reaction types are returned.
	 *
	 * @since 0.34.0
	 *
	 * @param string $discussion_url Full GitHub Discussion URL.
	 *
	 * @return array{ reactions: array<string,int>, comments: int|null }|null Null on request failure.
	 */
	protected function fetch_discussion_stats_from_github( string $discussion_url ): ?array {
		if ( ! preg_match( '#^https://github\.com/([^/]+/[^/]+)/discussions/(\d+)#', $discussion_url, $m ) ) {
			return null;
		}

		$api_url = sprintf(
			'https://api.github.com/repos/%s/discussions/%d',
			$m[1],
			(int) $m[2]
		);

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'GatherPress/' . GATHERPRESS_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		// Keep only known reaction types with a non-zero count.
		$raw       = is_array( $data['reactions'] ?? null ) ? $data['reactions'] : array();
		$reactions = array();
		foreach ( array_keys( self::REACTION_EMOJI ) as $type ) {
			if ( ! empty( $raw[ $type ] ) ) {
				$reactions[ $type ] = (int) $raw[ $type ];
			}
		}

		$comments = isset( $data['comments'] ) ? (int) $data['comments'] : null;

		return array( 'reactions' => $reactions, 'comments' => $comments );
	}

	/**
	 * Return the inline CSS for the Experiments page.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	protected function inline_styles(): string {
		return '
.gp-experiments-wrap .gp-exp-intro { color: #646970; margin-bottom: 1.5em; }
.gp-experiments-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 1.25rem;
	margin-top: 1rem;
}
.gp-exp-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 1.25rem 1.25rem 1rem;
	display: flex;
	flex-direction: column;
	gap: .6rem;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.gp-exp-card h3 { margin: 0; font-size: 1rem; line-height: 1.4; }
.gp-exp-card p  { margin: 0; color: #646970; font-size: .875rem; flex: 1; }
.gp-exp-meta { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #646970; }
.gp-exp-reaction,
.gp-exp-comments {
	display: inline-flex;
	align-items: center;
	gap: .25rem;
	border-radius: 2em;
	padding: .15em .65em;
	font-weight: 600;
}
.gp-exp-reaction {
	background: #f0f6ff;
	border: 1px solid #c2dbff;
	color: #2271b1;
	font-size: .8rem;
}
.gp-exp-comments {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	color: #3c434a;
}
.gp-exp-comments .dashicons { font-size: 14px; width: 14px; height: 14px; margin-top: 1px; }
.gp-exp-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .25rem; }
.gp-exp-actions .button { font-size: .8rem; line-height: 2; height: auto; padding: 0 .75rem; }
		';
	}
}
