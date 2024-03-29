<?php
/**
 * Render a single contributor list item.
 *
 * This code snippet is responsible for rendering a list item for a contributor
 * in GatherPress. It displays the contributor's name as a link to their
 * WordPress.org profile.
 *
 * @package GatherPress\Core
 * @param array $gatherpress_contributor An array containing contributor information.
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! isset( $gatherpress_contributor, $gatherpress_contributor_end ) ) {
	return;
}

$gatherpress_contributor['name'] = ! empty( $gatherpress_contributor['name'] ) ? $gatherpress_contributor['name'] : $gatherpress_contributor['slug'];
?>
<a id="<?php echo esc_attr( sprintf( 'gp-gatherpress_credit-%s', $gatherpress_contributor['slug'] ) ); ?>" href="<?php echo esc_url( sprintf( 'https://profiles.wordpress.org/%s/', $gatherpress_contributor['slug'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $gatherpress_contributor['name'] ); ?></a><?php echo esc_html( $gatherpress_contributor_end ); ?>
