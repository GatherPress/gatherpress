<?php
/**
 * Template: Tools > GatherPress Experiments admin page.
 *
 * Available variables:
 *   $experiments  array  List of experiment items from Experiments::get_experiments().
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
?>
<div class="wrap gp-experiments-wrap">
	<h1>
		<?php esc_html_e( 'GatherPress Experiments', 'gatherpress' ); ?>
	</h1>

	<p class="gp-exp-intro">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL to GitHub Discussions */
				__( 'These are active experiments being discussed in the <a href="%s" target="_blank" rel="noopener noreferrer">GatherPress GitHub Discussions</a>. Try each one in a Playground sandbox and cast your vote on GitHub to shape the roadmap.', 'gatherpress' ),
				'https://github.com/GatherPress/gatherpress/discussions'
			),
			array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
		);
		?>
	</p>

	<?php if ( empty( $experiments ) ) : ?>
		<p><?php esc_html_e( 'No experiments found.', 'gatherpress' ); ?></p>
	<?php else : ?>
		<div class="gp-experiments-grid">
			<?php foreach ( $experiments as $experiment ) :
				$title          = isset( $experiment['title'] )          ? (string) $experiment['title']          : '';
				$description    = isset( $experiment['description'] )    ? (string) $experiment['description']    : '';
				$discussion     = isset( $experiment['discussion'] )     ? (string) $experiment['discussion']     : '';
				$playground_url = isset( $experiment['playground_url'] ) ? (string) $experiment['playground_url'] : '';
				$reactions      = isset( $experiment['reactions'] ) && is_array( $experiment['reactions'] ) ? $experiment['reactions'] : array();
				$comments       = isset( $experiment['comments'] )       ? $experiment['comments']               : null;
			?>
			<div class="gp-exp-card">
				<h3><?php echo esc_html( $title ); ?></h3>

				<p><?php echo esc_html( $description ); ?></p>

				<div class="gp-exp-meta">
					<?php
					$reaction_emoji = GatherPress\Core\Settings\Experiments::REACTION_EMOJI;
					foreach ( $reaction_emoji as $type => $emoji ) :
						if ( empty( $reactions[ $type ] ) ) continue;
						$count = (int) $reactions[ $type ];
					?>
						<span class="gp-exp-reaction" title="<?php echo esc_attr( $type ); ?>">
							<span aria-hidden="true"><?php echo $emoji; // HTML entities, safe ?></span>
							<?php echo esc_html( number_format_i18n( $count ) ); ?>
						</span>
					<?php endforeach; ?>

					<?php if ( null !== $comments && $comments >= 0 ) : ?>
						<span class="gp-exp-comments">
							<span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of comments */
									_n( '%d comment', '%d comments', $comments, 'gatherpress' ),
									$comments
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>

				<div class="gp-exp-actions">
					<?php if ( ! empty( $discussion ) ) : ?>
						<a
							href="<?php echo esc_url( $discussion ); ?>"
							target="_blank"
							rel="noopener noreferrer"
							class="button button-secondary"
						>
							<?php esc_html_e( 'View Discussion', 'gatherpress' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'gatherpress' ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( ! empty( $playground_url ) ) : ?>
						<a
							href="<?php echo esc_url( $playground_url ); ?>"
							target="_blank"
							rel="noopener noreferrer"
							class="button button-primary"
						>
							<?php esc_html_e( '▶ Try in Playground', 'gatherpress' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'gatherpress' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
