<?php
/**
 * Render the GatherPress Network Settings page.
 *
 * Rendered under Network Admin → Settings → GatherPress. Presents the same
 * tabs as the per-site GatherPress settings (minus Tools), saving values to
 * a network-wide site option. A final "Network" tab configures which
 * settings subsites are forced to inherit from the network.
 *
 * @package GatherPress\Core
 * @param array  $sub_pages   Tabs to render, keyed by slug.
 * @param string $current_tab Slug of the active tab.
 * @param array  $config      Current network inheritance config.
 * @since 1.0.0
 */

use GatherPress\Core\Settings\Network;
use GatherPress\Core\Utility;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $sub_pages, $current_tab, $config ) ) {
	return;
}

$gatherpress_network_url = network_admin_url( 'settings.php' );
$gatherpress_is_network  = Network::NETWORK_TAB === $current_tab;
?>

<style>
	.gatherpress-allowlist__options {
		list-style: none;
		margin: 0;
		padding: 0;
	}
	.gatherpress-allowlist__options li {
		margin: 0;
		padding: 3px 0;
	}
	.gatherpress-allowlist__options label {
		align-items: center;
		display: inline-flex;
		gap: 6px;
	}
	.gatherpress-allowlist__bulk-actions {
		font-size: 13px;
	}
	.gatherpress-allowlist__group-actions {
		font-size: 12px;
		font-weight: 400;
		margin-left: 8px;
	}
</style>

<div class="wrap gatherpress-settings">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'GatherPress Network Settings', 'gatherpress' ); ?>
	</h1>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'gatherpress' ); ?></p>
		</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $sub_pages as $gatherpress_tab_slug => $gatherpress_tab ) : ?>
			<?php
			$gatherpress_active = ( $gatherpress_tab_slug === $current_tab ) ? 'nav-tab-active' : '';
			$gatherpress_href   = add_query_arg(
				array(
					'page' => Network::PAGE_SLUG,
					'tab'  => $gatherpress_tab_slug,
				),
				$gatherpress_network_url
			);
			?>
			<a
				class="<?php echo esc_attr( trim( 'nav-tab ' . $gatherpress_active ) ); ?>"
				href="<?php echo esc_url( $gatherpress_href ); ?>"
			>
				<?php echo esc_html( $gatherpress_tab['name'] ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<?php if ( $gatherpress_is_network ) : ?>
		<?php
		$gatherpress_enabled   = ! empty( $config['enabled'] );
		$gatherpress_inherited = array_flip( (array) ( $config['inherited'] ?? array() ) );
		$gatherpress_name      = sprintf( '%s[inherited][]', Network::OPTION_NAME );

		// Pre-filter the sub-page list so only groups that actually contain
		// at least one option are rendered (and empty sections are skipped).
		$gatherpress_groups = array();

		foreach ( $sub_pages as $gatherpress_sub_page_slug => $gatherpress_sub_page ) {
			if ( Network::NETWORK_TAB === $gatherpress_sub_page_slug ) {
				continue;
			}

			if ( empty( $gatherpress_sub_page['sections'] ) ) {
				continue;
			}

			$gatherpress_sections = array();

			foreach ( (array) $gatherpress_sub_page['sections'] as $gatherpress_section ) {
				if ( empty( $gatherpress_section['options'] ) ) {
					continue;
				}

				$gatherpress_sections[] = $gatherpress_section;
			}

			if ( empty( $gatherpress_sections ) ) {
				continue;
			}

			$gatherpress_groups[] = array(
				'name'     => $gatherpress_sub_page['name'],
				'sections' => $gatherpress_sections,
			);
		}
		?>

		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=' . Network::EDIT_ACTION ) ); ?>">
			<?php wp_nonce_field( Network::NONCE_ACTION ); ?>

			<h2><?php esc_html_e( 'Network Inheritance', 'gatherpress' ); ?></h2>
			<p>
				<?php
				esc_html_e(
					// phpcs:disable Generic.Files.LineLength.TooLong
					'Control whether individual sites in this network can change GatherPress settings locally, or must inherit them from the values set on the other tabs above.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				);
				?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'gatherpress' ); ?></th>
					<td>
						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( sprintf( '%s[enabled]', Network::OPTION_NAME ) ); ?>"
								value="1"
								<?php checked( $gatherpress_enabled ); ?>
							/>
							<?php
							esc_html_e(
								// phpcs:disable Generic.Files.LineLength.TooLong
								'Allow individual settings below to be inherited from the network.',
								// phpcs:enable Generic.Files.LineLength.TooLong
								'gatherpress'
							);
							?>
						</label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Inherited Settings', 'gatherpress' ); ?></h2>
			<p>
				<?php
				esc_html_e(
					// phpcs:disable Generic.Files.LineLength.TooLong
					'Check any setting that subsites must inherit from the network. Unchecked settings remain editable per site.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				);
				?>
			</p>

			<p class="gatherpress-allowlist__bulk-actions">
				<a href="#" data-gatherpress-toggle="all" data-gatherpress-state="1">
					<?php esc_html_e( 'Select all', 'gatherpress' ); ?>
				</a>
				<span aria-hidden="true"> | </span>
				<a href="#" data-gatherpress-toggle="all" data-gatherpress-state="0">
					<?php esc_html_e( 'Deselect all', 'gatherpress' ); ?>
				</a>
			</p>

			<?php foreach ( $gatherpress_groups as $gatherpress_group ) : ?>
				<div class="gatherpress-allowlist__group">
					<h3>
						<?php echo esc_html( $gatherpress_group['name'] ); ?>
						<span class="gatherpress-allowlist__group-actions">
							(<a href="#" data-gatherpress-toggle="group" data-gatherpress-state="1">
								<?php esc_html_e( 'select all', 'gatherpress' ); ?>
							</a>
							<span aria-hidden="true">|</span>
							<a href="#" data-gatherpress-toggle="group" data-gatherpress-state="0">
								<?php esc_html_e( 'deselect all', 'gatherpress' ); ?>
							</a>)
						</span>
					</h3>
					<table class="form-table" role="presentation">
					<?php foreach ( $gatherpress_group['sections'] as $gatherpress_section ) : ?>
						<tr>
							<th scope="row">
								<?php echo esc_html( $gatherpress_section['name'] ?? '' ); ?>
							</th>
							<td>
								<ul class="gatherpress-allowlist__options">
									<?php foreach ( (array) $gatherpress_section['options'] as $gatherpress_option_key => $gatherpress_option ) : ?>
										<li>
											<label>
												<input
													type="checkbox"
													name="<?php echo esc_attr( $gatherpress_name ); ?>"
													value="<?php echo esc_attr( $gatherpress_option_key ); ?>"
													<?php checked( isset( $gatherpress_inherited[ $gatherpress_option_key ] ) ); ?>
												/>
												<?php echo esc_html( $gatherpress_option['labels']['name'] ?? $gatherpress_option_key ); ?>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</td>
						</tr>
					<?php endforeach; ?>
					</table>
				</div>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save Network Settings', 'gatherpress' ) ); ?>
		</form>

		<script>
			( function () {
				var container = document.querySelector( '.gatherpress-settings' );

				if ( ! container ) {
					return;
				}

				var inheritedName = <?php echo wp_json_encode( sprintf( '%s[inherited][]', Network::OPTION_NAME ) ); ?>;

				function setAll( nodeList, checked ) {
					Array.prototype.forEach.call( nodeList, function ( cb ) {
						cb.checked = checked;
					} );
				}

				container.addEventListener( 'click', function ( event ) {
					var trigger = event.target.closest( '[data-gatherpress-toggle]' );

					if ( ! trigger ) {
						return;
					}

					event.preventDefault();

					var scope   = trigger.getAttribute( 'data-gatherpress-toggle' );
					var checked = '1' === trigger.getAttribute( 'data-gatherpress-state' );
					var root    = 'group' === scope
						? trigger.closest( '.gatherpress-allowlist__group' )
						: container;

					if ( ! root ) {
						return;
					}

					setAll(
						root.querySelectorAll( 'input[type="checkbox"][name="' + inheritedName + '"]' ),
						checked
					);
				} );
			} )();
		</script>
	<?php else : ?>
		<?php
		$gatherpress_current_page = Utility::prefix_key( $current_tab );
		$gatherpress_has_fields   = ! empty( $GLOBALS['wp_settings_fields'][ $gatherpress_current_page ] ?? array() );
		?>

		<?php if ( $gatherpress_has_fields ) : ?>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=' . Network::VALUES_EDIT_ACTION ) ); ?>">
				<?php wp_nonce_field( Network::VALUES_NONCE_ACTION ); ?>
				<input type="hidden" name="gatherpress_tab" value="<?php echo esc_attr( $current_tab ); ?>" />
				<?php do_settings_sections( $gatherpress_current_page ); ?>
				<?php submit_button( __( 'Save Settings', 'gatherpress' ) ); ?>
			</form>
		<?php else : ?>
			<?php
			/**
			 * Fires so tabs that render via the GatherPress settings section action
			 * (e.g. the Alpha sub-page) can emit their own content. Mirrors the
			 * per-site settings page template.
			 *
			 * @since 1.0.0
			 *
			 * @param string $page Prefixed page slug (e.g. `gatherpress_alpha`).
			 */
			do_action( 'gatherpress_settings_section', $gatherpress_current_page );
			?>
		<?php endif; ?>
	<?php endif; ?>
</div>
