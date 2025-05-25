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
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'RSVPs', 'gatherpress' ); ?></h1>
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
