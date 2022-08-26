<?php
/**
 * Credits template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $option, $gatherpress_credits ) ) {
	return;
}
?>
<ul class="gp-settings__credits">
	<?php foreach ( (array) $gatherpress_credits as $credit ) : ?>
	<li id="<?php echo esc_attr( sprintf( 'gp-credit-%s', $credit['slug'] ) ); ?>">
		<a href="<?php echo esc_url( sprintf( 'https://profiles.wordpress.org/%s/', $credit['slug'] ) ); ?>" target="_blank">
			<img alt="<?php echo esc_attr( $credit['name'] ); ?>" src="<?php echo esc_url( $credit['avatar_urls']['96'] ); ?>" />
			<div>
				<?php echo esc_html( $credit['name'] ); ?>
			</div>
		</a>
	</li>
	<?php endforeach; ?>
</ul>
