<?php
/**
 * Future events block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_max_posts = ( is_array( $attrs ) && ! empty( $attrs['maxNumberOfEvents'] ) ) ? intval( $attrs['maxNumberOfEvents'] ) : 5;
$gatherpress_max_posts = ( 0 > $gatherpress_max_posts ) ? 5 : $gatherpress_max_posts;
$gatherpress_query     = \GatherPress\Core\Query::get_instance()->get_future_events( $gatherpress_max_posts );
?>
<div class="gp-upcoming-events">
	<?php
	if ( $gatherpress_query->have_posts() ) {
		?>
		<?php
		while ( $gatherpress_query->have_posts() ) {
			$gatherpress_query->the_post();

			$gatherpress_event = new \GatherPress\Core\Event( get_the_ID() );

			?>
			<div class="gp-upcoming-events__container">
				<?php
				if ( has_post_thumbnail() ) {
					?>
					<figure class="gp-upcoming-events__image">
						<?php the_post_thumbnail( 'medium' ); ?>
					</figure>
					<?php
				}
				?>
				<div class="gp-upcoming-events__content">
					<h5 class="gp-upcoming-events__datetime">
						<?php echo esc_html( $gatherpress_event->get_datetime_start() ); ?>
					</h5>
					<h3 class="gp-upcoming-events__title">
						<a href="<?php the_permalink(); ?>" class="block">
							<?php the_title(); ?>
						</a>
					</h3>
					<div class="gp-upcoming-events__excerpt">
						<?php the_excerpt(); ?>
					</div>
				</div>
			</div>
			<?php
		}
		wp_reset_postdata();
	}
	?>
</div>
