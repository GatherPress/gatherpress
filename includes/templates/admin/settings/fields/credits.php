<?php
/**
 * Template for displaying credits in GatherPress settings.
 *
 * This template is used to display a list of credits, typically for contributors and project leads,
 * in GatherPress settings pages.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $option   The option name for retrieving the credits data.
 * @param array  $credits  An array of credit data containing names, slugs, and avatar URLs.
 */

if ( ! isset( $option, $credits ) ) {
	return;
}

?>
<ul class="gp-settings__credits">
	<?php foreach ( (array) $credits as $gatherpress_credit ) : ?>
		<?php
		$gatherpress_name = ! empty( $gatherpress_credit['name'] ) ? $gatherpress_credit['name'] : $gatherpress_credit['slug'];
		?>
		<li id="<?php echo esc_attr( sprintf( 'gp-gatherpress_credit-%s', $gatherpress_credit['slug'] ) ); ?>">
			<a href="<?php echo esc_url( sprintf( 'https://profiles.wordpress.org/%s/', $gatherpress_credit['slug'] ) ); ?>" target="_blank" rel="noopener">
				<img alt="<?php echo esc_attr( $gatherpress_name ); ?>" src="<?php echo esc_url( $gatherpress_credit['avatar_urls']['96'] ); ?>" />
				<div>
					<?php echo esc_html( $gatherpress_name ); ?>
				</div>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
