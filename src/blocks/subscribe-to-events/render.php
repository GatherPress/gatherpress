<?php
/**
 * Render Subscribe to Events block.
 *
 * Resolves calendar feed URLs from the block's scope attributes and links to
 * them. Feed URLs come from `Calendar\Feed_Url`, so the block honors the
 * `gatherpress_calendar_feed_url` filter.
 *
 * The `event` scope is context-aware: it reads the post ID from block context
 * (falling back to the queried post) and lists the current event's own iCal
 * download plus the feeds of its venue and its topics. Outside an event it
 * renders nothing.
 *
 * @package GatherPress\Core
 * @since TBD
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Feed_Url;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Post;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_scope       = $attributes['scope'] ?? '';
$gatherpress_link_format = $attributes['linkFormat'] ?? 'both';
$gatherpress_link_text   = trim( (string) ( $attributes['linkText'] ?? '' ) );
$gatherpress_subscribe   = trim( (string) ( $attributes['subscribeText'] ?? '' ) );
$gatherpress_ical_label  = '' !== $gatherpress_link_text ? $gatherpress_link_text : __( 'iCal feed', 'gatherpress' );
$gatherpress_webcal_text = '' !== $gatherpress_subscribe ? $gatherpress_subscribe : __( 'Subscribe', 'gatherpress' );

// Feeds to link to, each as url / source name / whether it is a one-off download.
$gatherpress_feeds = array();

if ( 'event' === $gatherpress_scope ) {
	$gatherpress_post_id = ! empty( $block->context['postId'] )
		? (int) $block->context['postId']
		: (int) get_the_ID();

	// Contextual feeds only exist on an event, so anything else renders nothing.
	if ( post_type_supports( (string) get_post_type( $gatherpress_post_id ), Event::SUPPORT ) ) {
		// The event's own iCal is a download, so it gets no subscribe link.
		$gatherpress_feeds[] = array(
			'url'      => (string) ( new Calendar( $gatherpress_post_id ) )->get_ical_url(),
			'source'   => '',
			'download' => true,
		);

		$gatherpress_venue = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $gatherpress_post_id );

		if ( $gatherpress_venue instanceof WP_Post ) {
			$gatherpress_feeds[] = array(
				'url'      => (string) Feed_Url::get(
					array(
						'scope'    => 'venue',
						'venue_id' => $gatherpress_venue->ID,
					)
				),
				'source'   => $gatherpress_venue->post_title,
				'download' => false,
			);
		}

		$gatherpress_topics = get_the_terms( $gatherpress_post_id, Topic::TAXONOMY );

		if ( is_array( $gatherpress_topics ) ) {
			foreach ( $gatherpress_topics as $gatherpress_topic ) {
				$gatherpress_feeds[] = array(
					'url'      => (string) Feed_Url::get(
						array(
							'scope'    => 'topic',
							'topic_id' => $gatherpress_topic->term_id,
						)
					),
					'source'   => $gatherpress_topic->name,
					'download' => false,
				);
			}
		}
	}
} else {
	$gatherpress_feeds[] = array(
		'url'      => (string) Feed_Url::get(
			array(
				'scope'     => $gatherpress_scope,
				'post_type' => (string) ( $attributes['postType'] ?? '' ),
				'venue_id'  => absint( $attributes['venueId'] ?? 0 ),
				'topic_id'  => absint( $attributes['topicId'] ?? 0 ),
			)
		),
		'source'   => '',
		'download' => false,
	);
}

// Build the visible links, skipping any feed that did not resolve. A scope
// that resolves to no feed renders nothing rather than a dead link.
$gatherpress_links = array();

foreach ( $gatherpress_feeds as $gatherpress_feed ) {
	if ( '' === $gatherpress_feed['url'] ) {
		continue;
	}

	$gatherpress_source = (string) $gatherpress_feed['source'];

	if ( in_array( $gatherpress_link_format, array( 'ical', 'both' ), true ) ) {
		$gatherpress_links[] = array(
			'url'  => $gatherpress_feed['url'],
			'text' => '' === $gatherpress_source
				? $gatherpress_ical_label
				: sprintf(
					/* translators: 1: Feed source name (venue or topic), 2: Link label. */
					__( '%1$s: %2$s', 'gatherpress' ),
					$gatherpress_source,
					$gatherpress_ical_label
				),
		);
	}

	// A one-off download has no subscription feed behind it, so it gets no webcal link.
	if ( ! $gatherpress_feed['download'] && in_array( $gatherpress_link_format, array( 'webcal', 'both' ), true ) ) {
		$gatherpress_links[] = array(
			'url'  => Feed_Url::to_webcal( $gatherpress_feed['url'] ),
			'text' => '' === $gatherpress_source
				? $gatherpress_webcal_text
				: sprintf(
					/* translators: 1: Feed source name (venue or topic), 2: Link label. */
					__( '%1$s: %2$s', 'gatherpress' ),
					$gatherpress_source,
					$gatherpress_webcal_text
				),
		);
	}
}

// A link format the block does not know about, or a context with no feed,
// leaves nothing to render.
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
