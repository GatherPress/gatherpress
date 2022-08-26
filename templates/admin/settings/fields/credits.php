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
//echo '<pre>';
//print_r($credits);
//echo '</pre>';
?>
<ul class="gp-settings__credits">
	<?php foreach ( $credits as $credit ) : ?>
	<li id="<?php echo esc_attr( sprintf( 'gp-credit-%s', $credit['slug'] ) ); ?>">
		<a href="<?php echo esc_url( sprintf( 'https://profiles.wordpress.org/%s/', $credit['slug'] ) ); ?>" target="_blank">
			<img src="<?php echo esc_url( $credit['avatar_urls']['96'] ); ?>" />
			<div><?php echo esc_html( $credit['name'] ); ?></div>
		</a>
	</li>
	<?php endforeach; ?>
</ul>
