<?php
/**
 * Class handles unit tests for the event email template.
 *
 * @package GatherPress\Core\Templates
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Templates;

use GatherPress\Core\Event;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Email.
 *
 * @coversNothing
 */
class Test_Event_Email extends Base {

	/**
	 * Tests the featured image uses bounded dimensions and email-safe attributes.
	 *
	 * @return void
	 */
	public function test_featured_image_uses_bounded_dimensions(): void {
		$event_id      = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$attachment_id = $this->factory()->post->create( array( 'post_type' => 'attachment' ) );
		// Set meta directly as the test theme does not register post-thumbnail support.
		update_post_meta( $event_id, '_thumbnail_id', $attachment_id );

		$image_downsize = static function () {
			return array( 'https://example.org/event.jpg', 1200, 800, true );
		};
		add_filter( 'image_downsize', $image_downsize );
		// The fixture attachment has no real file, so let the image check pass through.
		$is_image = '__return_true';
		add_filter( 'wp_attachment_is_image', $is_image );

		$output = Utility::render_template(
			GATHERPRESS_CORE_PATH . '/includes/templates/admin/emails/event-email.php',
			array(
				'event_id' => $event_id,
				'message'  => '',
			)
		);

		remove_filter( 'image_downsize', $image_downsize );
		remove_filter( 'wp_attachment_is_image', $is_image );

		$this->assertStringContainsString( 'src="https://example.org/event.jpg"', $output );
		$this->assertStringContainsString( 'width="600"', $output );
		$this->assertStringContainsString( 'height="400"', $output );
		$this->assertStringContainsString( 'max-width: 100%; height: auto;', $output );
		$this->assertStringNotContainsString( 'srcset=', $output );
		$this->assertStringNotContainsString( 'sizes=', $output );
		$this->assertStringNotContainsString( 'loading=', $output );
	}

	/**
	 * Tests the template omits the image when no featured image is set.
	 *
	 * @return void
	 */
	public function test_featured_image_is_omitted_when_not_set(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$output = Utility::render_template(
			GATHERPRESS_CORE_PATH . '/includes/templates/admin/emails/event-email.php',
			array(
				'event_id' => $event_id,
				'message'  => '',
			)
		);

		$this->assertStringNotContainsString( '<img', $output );
	}

	/**
	 * Tests the template omits an image when its source cannot be resolved.
	 *
	 * @return void
	 */
	public function test_featured_image_is_omitted_when_source_is_unavailable(): void {
		$event_id      = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$attachment_id = $this->factory()->post->create( array( 'post_type' => 'attachment' ) );
		// Set meta directly as the test theme does not register post-thumbnail support.
		update_post_meta( $event_id, '_thumbnail_id', $attachment_id );

		$image_downsize = static function () {
			return false;
		};
		add_filter( 'image_downsize', $image_downsize );

		$output = Utility::render_template(
			GATHERPRESS_CORE_PATH . '/includes/templates/admin/emails/event-email.php',
			array(
				'event_id' => $event_id,
				'message'  => '',
			)
		);

		remove_filter( 'image_downsize', $image_downsize );

		$this->assertStringNotContainsString( '<img', $output );
	}
}
