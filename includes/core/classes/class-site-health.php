<?php
/**
 * Site Health checks for GatherPress.
 *
 * Registers WordPress Site Health tests that surface configuration issues
 * affecting GatherPress features.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Site_Health.
 *
 * Manages GatherPress Site Health tests.
 *
 * @since 1.0.0
 */
class Site_Health {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Site Health test identifier for the pretty permalinks check.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const PRETTY_PERMALINKS_TEST = 'gatherpress_pretty_permalinks';

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for Site Health tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'site_status_tests', array( $this, 'register_site_status_tests' ) );
	}

	/**
	 * Register GatherPress direct Site Health tests.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, array<string, mixed>>> $tests Existing Site Health tests.
	 * @return array<string, array<string, array<string, mixed>>> Updated Site Health tests.
	 */
	public function register_site_status_tests( array $tests ): array {
		$tests['direct'][ self::PRETTY_PERMALINKS_TEST ] = array(
			'label' => __( 'GatherPress pretty permalinks', 'gatherpress' ),
			'test'  => array( $this, 'test_pretty_permalinks' ),
		);

		return $tests;
	}

	/**
	 * Site Health test for WordPress pretty permalinks.
	 *
	 * GatherPress rewrite rules for event and venue URLs, feeds, and calendar
	 * endpoints require a permalink structure other than Plain.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_pretty_permalinks(): array {
		$result = array(
			'label'       => __( 'Pretty permalinks are enabled', 'gatherpress' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'GatherPress', 'gatherpress' ),
				'color' => 'green',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__(
					'Pretty permalinks are enabled, so GatherPress event and venue URLs and feeds work correctly.',
					'gatherpress'
				)
			),
			'actions'     => '',
			'test'        => self::PRETTY_PERMALINKS_TEST,
		);

		if ( empty( get_option( 'permalink_structure' ) ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Pretty permalinks are not enabled', 'gatherpress' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf(
				'<p>%s</p>',
				esc_html__(
					'GatherPress needs pretty permalinks for events and venues. Plain permalinks may cause 404 errors.',
					'gatherpress'
				)
			);
			$result['actions']        = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'options-permalink.php' ) ),
				esc_html__( 'Change permalink settings', 'gatherpress' )
			);
		}

		return $result;
	}
}
