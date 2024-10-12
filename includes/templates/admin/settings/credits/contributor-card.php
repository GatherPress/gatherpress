<?php
/**
 * Render a single contributor list item.
 *
 * This code snippet is responsible for rendering a list item for a contributor
 * in GatherPress. It displays the contributor's name, avatar, and a link to their
 * WordPress.org profile.
 *
 * @package GatherPress\Core
 * @param array $gatherpress_contributor An array containing contributor information.
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $gatherpress_contributor ) ) {
	return;
}

$gatherpress_contributor['name'] = ! empty( $gatherpress_contributor['name'] ) ? $gatherpress_contributor['name'] : $gatherpress_contributor['slug'];
$gatherpress_role                = ! empty( $gatherpress_role ) ? $gatherpress_role : '';
?>
<li id="<?php echo esc_attr( sprintf( 'gatherpress-credit-%s', $gatherpress_contributor['slug'] ) ); ?>">
	<a href="<?php echo esc_url( sprintf( 'https://profiles.wordpress.org/%s/', $gatherpress_contributor['slug'] ) ); ?>" target="_blank" rel="noopener">
		<img alt="<?php echo esc_attr( $gatherpress_contributor['name'] ); ?>" src="<?php echo esc_url( $gatherpress_contributor['avatar_urls']['96'] ); ?>" />
		<span>
			<?php echo esc_html( $gatherpress_contributor['name'] ); ?>
		</span>
	</a>
	<?php
	if ( ! empty( $gatherpress_role ) ) :
		?>
		<span>
			<?php echo esc_html( $gatherpress_role ); ?>
		</span>
		<?php
	endif;
	?>
</li>
