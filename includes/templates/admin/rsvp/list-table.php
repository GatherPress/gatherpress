<?php
/**
 * Admin RSVP list table template.
 *
 * @package GatherPress\Core
 */

defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;

if ( ! isset( $rsvp_table, $search_term, $status, $event ) ) {
	return;
}

$rsvp_table->prepare_items();
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
	<?php
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$gatherpress_post_id = 0;
	if ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {
		$gatherpress_post_id = intval( $_REQUEST['post_id'] );
	}

	if ( $gatherpress_post_id ) {
		printf(
			/* translators: %s: Event title. */
			esc_html__( 'RSVPs for &#8220;%s&#8221;', 'gatherpress' ),
			esc_html( wp_html_excerpt( get_the_title( $gatherpress_post_id ), 50, '&hellip;' ) )
		);
	} else {
		esc_html_e( 'RSVPs', 'gatherpress' );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	?>
	</h1>

	<?php
	if ( $gatherpress_post_id ) {
		printf(
			'<a href="%1$s" class="comments-view-item-link">%2$s</a>',
			esc_url( get_permalink( $gatherpress_post_id ) ),
			esc_html__( 'View Event', 'gatherpress' )
		);
	}
	?>

	<hr class="wp-header-end">

	<form method="get">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( Event::POST_TYPE ); ?>" />
		<input type="hidden" name="page" value="<?php echo esc_attr( Rsvp::COMMENT_TYPE ); ?>" />
		<p class="search-box">
			<label class="screen-reader-text" for="rsvp-search-input">
				<?php echo esc_html__( 'Search RSVPs', 'gatherpress' ); ?>
			</label>
			<input type="search" id="rsvp-search-input" name="s"
					value="<?php echo esc_attr( $search_term ); ?>" />
			<input type="submit" id="search-submit" class="button"
					value="<?php echo esc_attr__( 'Search RSVPs', 'gatherpress' ); ?>" />
		</p>

		<?php if ( ! empty( $status ) ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
		<?php endif; ?>

		<?php if ( ! empty( $event ) ) : ?>
			<input type="hidden" name="event" value="<?php echo esc_attr( $event ); ?>" />
		<?php endif; ?>
	</form>

	<?php $rsvp_table->process_bulk_action(); ?>

	<form method="post">
		<?php
		// Display the views.
		$gatherpress_views = $rsvp_table->get_views();
		echo '<ul class="subsubsub">';
		foreach ( $gatherpress_views as $gatherpress_class => $gatherpress_view ) {
			$gatherpress_views[ $gatherpress_class ] = "\t<li class='$gatherpress_class'>$gatherpress_view";
		}
		echo wp_kses_post( implode( " |</li>\n", $gatherpress_views ) . "</li>\n" );
		echo '</ul>';

		$rsvp_table->display();
		?>
	</form>
</div>
