<?php
/**
 * Render Subscribe to Events block.
 *
 * Resolves a calendar feed URL from the block's scope attributes and links to
 * it. The feed URL itself is built by `Calendar\Feed_Url`, so the block and the
 * `<link rel="alternate">` tags in `<head>` cannot disagree about where a feed
 * lives.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\Feed_Url;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_scope = $attributes['scope'] ?? '';

$gatherpress_feed_url = Feed_Url::get(
	array(
		'scope'     => $gatherpress_scope,
		'post_type' => (string) ( $attributes['postType'] ?? '' ),
		'venue_id'  => absint( $attributes['venueId'] ?? 0 ),
		'topic_id'  => absint( $attributes['topicId'] ?? 0 ),
	)
);

// A scope that resolves to no feed renders nothing rather than a dead link.
if ( false === $gatherpress_feed_url || '' === $gatherpress_feed_url ) {
	return;
}

$gatherpress_link_format = $attributes['linkFormat'] ?? 'both';
$gatherpress_link_text   = trim( (string) ( $attributes['linkText'] ?? '' ) );
$gatherpress_subscribe   = trim( (string) ( $attributes['subscribeText'] ?? '' ) );

$gatherpress_links = array();

if ( in_array( $gatherpress_link_format, array( 'ical', 'both' ), true ) ) {
	$gatherpress_links[] = array(
		'url'  => $gatherpress_feed_url,
		'text' => '' !== $gatherpress_link_text ? $gatherpress_link_text : __( 'iCal feed', 'gatherpress' ),
	);
}

if ( in_array( $gatherpress_link_format, array( 'webcal', 'both' ), true ) ) {
	$gatherpress_links[] = array(
		'url'  => Feed_Url::to_webcal( $gatherpress_feed_url ),
		'text' => '' !== $gatherpress_subscribe ? $gatherpress_subscribe : __( 'Subscribe', 'gatherpress' ),
	);
}

// A link format the block does not know about leaves nothing to render.
if ( array() === $gatherpress_links ) {
	return;
}

?>
<ul <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'gatherpress-subscribe-to-events' ) ) ); ?>>
	<?php foreach ( $gatherpress_links as $gatherpress_link ) : ?>
		<li class="gatherpress-subscribe-to-events__item">
			<a class="gatherpress-subscribe-to-events__link" href="<?php echo esc_url( $gatherpress_link['url'] ); ?>">
				<?php echo esc_html( $gatherpress_link['text'] ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
