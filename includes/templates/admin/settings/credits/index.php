<?php
/**
 * Render the Credits section in GatherPress settings.
 *
 * This code snippet is responsible for rendering the "Credits" section in the
 * GatherPress settings. It displays the project leads, the noteworthy
 * contributors for this release, and everyone who contributed to it. It also includes links to contributors' profiles.
 *
 * @package GatherPress\Core
 * @param array $credits An array containing contributor information.
 * @since 0.27.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Utility;

if ( ! isset( $credits ) ) {
	return;
}
?>

<div class="gatherpress-settings__credits">
	<h2><?php esc_html_e( 'Credits', 'gatherpress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Meet the folks behind GatherPress. Want to see your name here?', 'gatherpress' ); ?>
		<a href="https://github.com/GatherPress/gatherpress" rel="noopener" target="_blank"><?php esc_html_e( 'Get Involved', 'gatherpress' ); ?></a>.
	</p>
	<div class="gatherpress-settings__credits-wrapper">
		<h3><?php esc_html_e( 'Noteworthy Contributors', 'gatherpress' ); ?></h3>
		<ul class="gatherpress-settings__credits-featured">
			<?php
			foreach ( $credits['leads'] as $gatherpress_contributor ) :
				Utility::render_template(
					sprintf( '%s/includes/templates/admin/settings/credits/contributor-card.php', GATHERPRESS_CORE_PATH ),
					array(
						'gatherpress_contributor' => $gatherpress_contributor,
						'gatherpress_role'        => __( 'Lead', 'gatherpress' ),
					),
					true
				);
			endforeach;
			foreach ( $credits['noteworthy'] as $gatherpress_contributor ) :
				Utility::render_template(
					sprintf( '%s/includes/templates/admin/settings/credits/contributor-card.php', GATHERPRESS_CORE_PATH ),
					array(
						'gatherpress_contributor' => $gatherpress_contributor,
					),
					true
				);
			endforeach;
			?>
		</ul>
		<h3>
			<?php
			/* translators: %s: GatherPress version. */
			printf( esc_html__( 'Contributors to GatherPress %s', 'gatherpress' ), esc_html( $credits['version'] ) );
			?>
		</h3>
		<div class="gatherpress-settings__credits-list">
			<?php
			$gatherpress_contributor_links = array();

			foreach ( $credits['contributors'] ?? array() as $gatherpress_contributor ) :
				$gatherpress_contributor_links[] = trim(
					Utility::render_template(
						sprintf( '%s/includes/templates/admin/settings/credits/contributor-link.php', GATHERPRESS_CORE_PATH ),
						array( 'gatherpress_contributor' => $gatherpress_contributor )
					)
				);
			endforeach;

			if ( ! empty( $gatherpress_contributor_links ) ) {
				// Core's list separators are already translated in every locale.
				echo wp_kses_post(
					sprintf(
						/* translators: %s: List of contributor names. */
						__( '%s.', 'gatherpress' ),
						wp_sprintf_l( '%l', $gatherpress_contributor_links )
					)
				);
			}
			?>
		</div>
	</div>
</div>
