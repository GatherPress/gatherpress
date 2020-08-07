<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package gatherpress
 */
get_header();
$event = GatherPress\Inc\Event::get_instance();
?>

<div class="bg-light p-md-5">
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="entry-header">

			<div class="flex">
				<div class="w-9/12">
					<h3 class="text-2xl">
						<?php echo esc_html( $event->get_datetime_start( get_the_ID(), 'l, F j, Y' ) ); ?>
					</h3>
				</div>
				<div class="w-3/12">
					<div id="attendance_button_container"></div>
				</div>
			</div>

			<?php the_title( '<h1 class="entry-title text-4xl">', '</h1>' ); ?>
		</header><!-- .entry-header -->

		<?php gatherpress_post_thumbnail(); ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'gatherpress' ),
					'after'  => '</div>',
				)
			);
			?>
		</div><!-- .entry-content -->

		<footer class="entry-footer">
			<?php gatherpress_entry_footer(); ?>
		</footer><!-- .entry-footer -->
	</article><!-- #post-<?php the_ID(); ?> -->
</div>

<div id="attendance_container"></div>
<?php
get_sidebar();
get_footer();
