<?php
/**
 * Credits template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $option, $credits ) ) {
	return;
}
?>
<ul class="gp-settings__credits">
	<?php foreach ( (array) $credits as $gatherpress_credit ) : ?>
	<li id="<?php echo esc_attr( sprintf( 'gp-gatherpress_credit-%s', $gatherpress_credit['slug'] ) ); ?>">
		<a href="<?php echo esc_url( sprintf( 'https://profiles.wordpress.org/%s/', $gatherpress_credit['slug'] ) ); ?>" target="_blank">
			<img alt="<?php echo esc_attr( $gatherpress_credit['name'] ); ?>" src="<?php echo esc_url( $gatherpress_credit['avatar_urls']['96'] ); ?>" />
			<div>
				<?php echo esc_html( $gatherpress_credit['name'] ); ?>
			</div>
		</a>
	</li>
	<?php endforeach; ?>
</ul>
