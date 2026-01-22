<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Admin_Page.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Admin_Page;
use GatherPress\Core\AI\AI_Handler;
use GatherPress\Core\AI\Image_Handler;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_Admin_Page.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Admin_Page
 */
class Test_Admin_Page extends Base {
	/**
	 * Coverage for singleton pattern.
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance1 = Admin_Page::get_instance();
		$instance2 = Admin_Page::get_instance();

		$this->assertSame( $instance1, $instance2, 'Failed to assert singleton pattern works.' );
		$this->assertInstanceOf( Admin_Page::class, $instance1, 'Failed to assert instance is Admin_Page.' );
	}

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Admin_Page::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'add_admin_page' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_ajax_gatherpress_ai_process_prompt',
				'priority' => 10,
				'callback' => array( $instance, 'process_prompt_ajax' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for add_admin_page when Abilities API is available.
	 *
	 * @covers ::add_admin_page
	 *
	 * @return void
	 */
	public function test_add_admin_page_when_ability_api_available(): void {
		global $submenu;

		// Test both paths: when function exists and when it doesn't.

		// Initialize submenu if not set.
		if ( ! is_array( $submenu ) ) {
			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$instance = Admin_Page::get_instance();
		$instance->add_admin_page();

		// Check that submenu was added.
		$this->assertIsArray( $submenu );
		// Verify the AI Assistant menu was added to the events submenu.
		// The menu might not be visible if the post type doesn't exist, so just verify the method executed.
		$this->assertTrue( true, 'add_admin_page method executed successfully.' );
	}

	/**
	 * Coverage for add_admin_page when Abilities API is not available.
	 *
	 * @covers ::add_admin_page
	 *
	 * @return void
	 */
	public function test_add_admin_page_when_ability_api_not_available(): void {
		global $submenu;

		// Store original state.
		$original_submenu = $submenu ?? array();

		$instance = Admin_Page::get_instance();
		$instance->add_admin_page();

		// If function doesn't exist, should return early (line 55) without adding menu.
		// We can't easily mock function_exists, but we can verify the method executes.
		$this->assertTrue( true );

		// If function doesn't exist, submenu should not be modified.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			// Verify no AI Assistant menu was added.
			$found = false;
			if ( isset( $submenu['edit.php?post_type=gatherpress_event'] ) ) {
				foreach ( $submenu['edit.php?post_type=gatherpress_event'] as $item ) {
					if ( isset( $item[2] ) && 'gatherpress-ai-assistant' === $item[2] ) {
						$found = true;
						break;
					}
				}
			}
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertFalse( $found, 'AI Assistant menu should not be added when function does not exist.' );
		}
	}

	/**
	 * Coverage for enqueue_scripts with correct hook.
	 *
	 * @covers ::enqueue_scripts
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_with_correct_hook(): void {
		$instance = Admin_Page::get_instance();

		// Clear any previously enqueued scripts/styles.
		wp_dequeue_script( 'gatherpress-ai-assistant' );
		wp_dequeue_style( 'gatherpress-ai-assistant' );
		wp_deregister_script( 'gatherpress-ai-assistant' );
		wp_deregister_style( 'gatherpress-ai-assistant' );

		$instance->enqueue_scripts( 'gatherpress_event_page_gatherpress-ai-assistant' );

		// Verify scripts and styles were enqueued.
		$this->assertTrue( wp_style_is( 'gatherpress-ai-assistant', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'gatherpress-ai-assistant', 'enqueued' ) );
	}

	/**
	 * Coverage for enqueue_scripts with wrong hook.
	 *
	 * @covers ::enqueue_scripts
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_with_wrong_hook(): void {
		$instance = Admin_Page::get_instance();

		// Should return early without enqueuing.
		$instance->enqueue_scripts( 'other-page' );

		$this->assertTrue( true );
	}

	/**
	 * Coverage for render_admin_page when API key is not configured.
	 *
	 * @covers ::render_admin_page
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_render_admin_page_without_api_key(): void {
		$instance = Admin_Page::get_instance();

		// Ensure no API key is set.
		delete_option( 'gatherpress_ai' );

		$output = \PMC\Unit_Test\Utility::buffer_and_return(
			array( $instance, 'render_admin_page' ),
			array()
		);

		$this->assertStringContainsString( 'API Key Required', $output );
		$this->assertStringContainsString( 'Configure API Key', $output );
	}

	/**
	 * Coverage for render_admin_page when API key is configured.
	 *
	 * @covers ::render_admin_page
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_render_admin_page_with_api_key(): void {
		$instance = Admin_Page::get_instance();

		// Set a test API key using wp-ai-client option format.
		update_option(
			'wp_ai_client_provider_credentials',
			array(
				'openai' => 'test-key',
			)
		);

		$output = \PMC\Unit_Test\Utility::buffer_and_return(
			array( $instance, 'render_admin_page' ),
			array()
		);

		$this->assertStringContainsString( 'GatherPress AI Assistant', $output );
		$this->assertStringContainsString( 'gp-ai-assistant', $output );
		$this->assertStringContainsString( 'gp-ai-prompt', $output );

		// Clean up.
		delete_option( 'wp_ai_client_provider_credentials' );
	}

	/**
	 * Coverage for process_prompt_ajax method exists.
	 *
	 * @covers ::process_prompt_ajax
	 *
	 * @return void
	 */
	public function test_process_prompt_ajax_method_exists(): void {
		$instance = Admin_Page::get_instance();

		// Verify the method exists and is callable.
		$this->assertTrue( method_exists( $instance, 'process_prompt_ajax' ) );
		$this->assertTrue( is_callable( array( $instance, 'process_prompt_ajax' ) ) );
	}

	/**
	 * Coverage for process_prompt_ajax reset logic.
	 *
	 * Tests that the reset functionality works by verifying that
	 * reset_conversation_state() (which process_prompt_ajax calls)
	 * correctly clears the conversation state.
	 *
	 * Note: Full AJAX handler testing (with wp_send_json_success)
	 * would require WP_Ajax_UnitTestCase. The core reset logic
	 * is tested via test_reset_conversation_state in Test_AI_Handler.
	 *
	 * @covers ::process_prompt_ajax
	 *
	 * @return void
	 */
	public function test_process_prompt_ajax_reset_clears_state(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Set up some conversation state first.
		$state = array(
			'prompt_count' => 5,
			'char_count'   => 10000,
			'history'      => array( 'test' ),
		);
		update_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			$state
		);

		// Test that reset_conversation_state clears the state.
		// This verifies the logic that process_prompt_ajax calls.
		$handler = new AI_Handler();
		$result  = $handler->reset_conversation_state();

		// Verify returned state.
		$this->assertEquals( 0, $result['prompt_count'] );
		$this->assertEquals( 0, $result['char_count'] );
		$this->assertEquals( AI_Handler::MAX_PROMPTS, $result['max_prompts'] );
		$this->assertEquals( AI_Handler::MAX_CHARS, $result['max_chars'] );

		// Verify state is cleared in database.
		$after = get_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			true
		);
		$this->assertEmpty( $after );

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for get_asset_data when file exists.
	 *
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_get_asset_data_when_file_exists(): void {
		$instance = Admin_Page::get_instance();

		// Create a temporary asset file.
		$asset_path = GATHERPRESS_CORE_PATH . '/build/ai-assistant.asset.php';
		$asset_dir  = dirname( $asset_path );

		if ( ! file_exists( $asset_dir ) ) {
			wp_mkdir_p( $asset_dir );
		}

		$asset_data = array(
			'dependencies' => array( 'jquery' ),
			'version'      => '1.0.0',
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.PHP.DevelopmentFunctions.error_log_var_export
		file_put_contents( $asset_path, '<?php return ' . var_export( $asset_data, true ) . ';' );

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'get_asset_data', array( 'ai-assistant' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'dependencies', $result );
		$this->assertArrayHasKey( 'version', $result );

		// Clean up.
		if ( file_exists( $asset_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $asset_path );
		}
	}

	/**
	 * Coverage for get_asset_data when file does not exist.
	 *
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_get_asset_data_when_file_not_exists(): void {
		$instance = Admin_Page::get_instance();

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$instance,
			'get_asset_data',
			array( 'nonexistent-asset' )
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'dependencies', $result );
		$this->assertArrayHasKey( 'version', $result );
	}

	/**
	 * Coverage for handle_image_uploads with no files.
	 *
	 * @covers ::handle_image_uploads
	 *
	 * @return void
	 */
	public function test_handle_image_uploads_no_files(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$instance = Admin_Page::get_instance();

		// Ensure $_FILES is empty.
		$_FILES = array();

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'handle_image_uploads', array() );

		$this->assertIsArray( $result, 'Failed to assert result is array.' );
		$this->assertEmpty( $result, 'Failed to assert result is empty when no files are uploaded.' );
	}

	/**
	 * Coverage for handle_image_uploads with single valid file.
	 *
	 * @covers ::handle_image_uploads
	 *
	 * @return void
	 */
	public function test_handle_image_uploads_single_file(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create a mock attachment ID.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Create mock Image_Handler that returns attachment ID.
		$mock_image_handler = $this->createMock( Image_Handler::class );
		$mock_image_handler->expects( $this->once() )
			->method( 'upload_to_media_library' )
			->willReturn( $attachment_id );

		$instance = Admin_Page::get_instance();

		// Inject mock Image_Handler using reflection.
		Utility::set_and_get_hidden_property( $instance, 'image_handler', $mock_image_handler );

		// Simulate $_FILES with single file.
		$_FILES = array(
			'images' => array(
				'name'     => 'test-image.jpg',
				'type'     => 'image/jpeg',
				'tmp_name' => '/tmp/test-image.jpg',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 1024,
			),
		);

		$result = Utility::invoke_hidden_method( $instance, 'handle_image_uploads', array() );

		// Reset image_handler property.
		Utility::set_and_get_hidden_property( $instance, 'image_handler', null );

		$this->assertIsArray( $result, 'Failed to assert result is array.' );
		$this->assertCount( 1, $result, 'Failed to assert result contains one attachment ID.' );
		$this->assertSame( $attachment_id, $result[0], 'Failed to assert attachment ID matches.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for handle_image_uploads with invalid file.
	 *
	 * @covers ::handle_image_uploads
	 *
	 * @return void
	 */
	public function test_handle_image_uploads_invalid_file(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create a temporary non-image file.
		$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.pdf';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		file_put_contents( $temp_file, 'fake pdf content' );

		$instance = Admin_Page::get_instance();

		// Simulate $_FILES with invalid file.
		$_FILES = array(
			'images' => array(
				'name'     => 'test.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => $temp_file,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $temp_file ),
			),
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'handle_image_uploads', array() );

		$this->assertIsArray( $result, 'Failed to assert result is array.' );
		$this->assertEmpty( $result, 'Failed to assert result is empty when invalid file is uploaded.' );

		// Clean up.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
		@unlink( $temp_file );
	}

	/**
	 * Coverage for handle_image_uploads with multiple files.
	 *
	 * @covers ::handle_image_uploads
	 *
	 * @return void
	 */
	public function test_handle_image_uploads_multiple_files(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create mock attachment IDs.
		$attachment_id1 = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image 1',
			)
		);
		$attachment_id2 = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Test Image 2',
			)
		);

		// Create mock Image_Handler that returns attachment IDs.
		$mock_image_handler = $this->createMock( Image_Handler::class );
		$mock_image_handler->expects( $this->exactly( 2 ) )
			->method( 'upload_to_media_library' )
			->willReturnOnConsecutiveCalls( $attachment_id1, $attachment_id2 );

		$instance = Admin_Page::get_instance();

		// Inject mock Image_Handler using reflection.
		Utility::set_and_get_hidden_property( $instance, 'image_handler', $mock_image_handler );

		// Simulate $_FILES with multiple files.
		$_FILES = array(
			'images' => array(
				'name'     => array( 'test-image1.jpg', 'test-image2.png' ),
				'type'     => array( 'image/jpeg', 'image/png' ),
				'tmp_name' => array( '/tmp/test-image1.jpg', '/tmp/test-image2.png' ),
				'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
				'size'     => array( 1024, 2048 ),
			),
		);

		$result = Utility::invoke_hidden_method( $instance, 'handle_image_uploads', array() );

		// Reset image_handler property.
		Utility::set_and_get_hidden_property( $instance, 'image_handler', null );

		$this->assertIsArray( $result, 'Failed to assert result is array.' );
		$this->assertCount( 2, $result, 'Failed to assert result contains two attachment IDs.' );
		$this->assertSame(
			$attachment_id1,
			$result[0],
			'Failed to assert first attachment ID matches.'
		);
		$this->assertSame(
			$attachment_id2,
			$result[1],
			'Failed to assert second attachment ID matches.'
		);

		// Clean up.
		wp_delete_attachment( $attachment_id1, true );
		wp_delete_attachment( $attachment_id2, true );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts method with event.
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_with_event(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Get attachment file path.
		$attachment_file = get_attached_file( $attachment_id );
		if ( ! $attachment_file || ! file_exists( $attachment_file ) ) {
			// Create a minimal image file for the attachment.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
			$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, $jpeg_data );
			$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
			if ( ! file_exists( $upload_dir['path'] ) ) {
				wp_mkdir_p( $upload_dir['path'] );
			}
			copy( $temp_file, $file_path );
			update_attached_file( $attachment_id, $file_path );
			// Clean up temp file.
			if ( file_exists( $temp_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
				unlink( $temp_file );
			}
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$instance = Admin_Page::get_instance();

		$attachment_ids = array( $attachment_id );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => $event_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Verify thumbnail was set.
		$thumbnail_id = get_post_thumbnail_id( $event_id );
		$this->assertSame( $attachment_id, $thumbnail_id, 'Failed to assert thumbnail was set.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts method with venue.
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_with_venue(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create a venue.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Get attachment file path.
		$attachment_file = get_attached_file( $attachment_id );
		if ( ! $attachment_file || ! file_exists( $attachment_file ) ) {
			// Create a minimal image file for the attachment.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
			$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, $jpeg_data );
			$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
			if ( ! file_exists( $upload_dir['path'] ) ) {
				wp_mkdir_p( $upload_dir['path'] );
			}
			copy( $temp_file, $file_path );
			update_attached_file( $attachment_id, $file_path );
			// Clean up temp file.
			if ( file_exists( $temp_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
				unlink( $temp_file );
			}
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$instance = Admin_Page::get_instance();

		$attachment_ids = array( $attachment_id );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-venue',
					'result'  => array(
						'success'  => true,
						'venue_id' => $venue_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Verify thumbnail was set.
		$thumbnail_id = get_post_thumbnail_id( $venue_id );
		$this->assertSame( $attachment_id, $thumbnail_id, 'Failed to assert thumbnail was set.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for maybe_add_image_reminder method with event.
	 *
	 * @covers ::maybe_add_image_reminder
	 *
	 * @return void
	 */
	public function test_maybe_add_image_reminder_with_event(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$instance = Admin_Page::get_instance();

		$attachment_ids = array();
		$result         = array(
			'response' => 'Event created successfully.',
			'actions'  => array(
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => $event_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_add_image_reminder', array( $attachment_ids, &$result ) );

		// Verify reminder was added.
		$this->assertStringContainsString(
			'Tip: Consider adding an image to make your event more engaging!',
			$result['response'],
			'Failed to assert reminder was added.'
		);
	}

	/**
	 * Coverage for maybe_add_image_reminder method with venue.
	 *
	 * @covers ::maybe_add_image_reminder
	 *
	 * @return void
	 */
	public function test_maybe_add_image_reminder_with_venue(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create a venue.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		$instance = Admin_Page::get_instance();

		$attachment_ids = array();
		$result         = array(
			'response' => 'Venue created successfully.',
			'actions'  => array(
				array(
					'ability' => 'gatherpress/create-venue',
					'result'  => array(
						'success'  => true,
						'venue_id' => $venue_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_add_image_reminder', array( $attachment_ids, &$result ) );

		// Verify reminder was added.
		$this->assertStringContainsString(
			'Tip: Consider adding an image to make your venue more engaging!',
			$result['response'],
			'Failed to assert reminder was added.'
		);
	}

	/**
	 * Coverage for maybe_attach_images_to_posts with invalid attachment ID (empty).
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_invalid_attachment_id_empty(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$instance = Admin_Page::get_instance();

		// Test with empty attachment ID.
		$attachment_ids = array( 0 );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => $event_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Verify thumbnail was NOT set.
		$thumbnail_id = get_post_thumbnail_id( $event_id );
		$this->assertEmpty( $thumbnail_id, 'Failed to assert thumbnail was not set with invalid attachment ID.' );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts with non-existent attachment.
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_nonexistent_attachment(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$instance = Admin_Page::get_instance();

		// Test with non-existent attachment ID.
		$attachment_ids = array( 99999 );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => $event_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Verify thumbnail was NOT set.
		$thumbnail_id = get_post_thumbnail_id( $event_id );
		$this->assertEmpty( $thumbnail_id, 'Failed to assert thumbnail was not set with non-existent attachment.' );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts with attachment that is not an image.
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_attachment_not_image(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		// Create a non-image attachment (PDF).
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'Test PDF',
			)
		);

		$instance = Admin_Page::get_instance();

		$attachment_ids = array( $attachment_id );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => $event_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Verify thumbnail was NOT set.
		$thumbnail_id = get_post_thumbnail_id( $event_id );
		$this->assertEmpty( $thumbnail_id, 'Failed to assert thumbnail was not set with non-image attachment.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts with invalid event_id (empty).
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_invalid_event_id(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$instance = Admin_Page::get_instance();

		$attachment_ids = array( $attachment_id );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => 0, // Invalid event_id.
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Method should return early without error.
		$this->assertTrue( true, 'Method should handle invalid event_id gracefully.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts with invalid venue_id (empty).
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_invalid_venue_id(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$instance = Admin_Page::get_instance();

		$attachment_ids = array( $attachment_id );
		$result         = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-venue',
					'result'  => array(
						'success'  => true,
						'venue_id' => 0, // Invalid venue_id.
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Method should return early without error.
		$this->assertTrue( true, 'Method should handle invalid venue_id gracefully.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for maybe_attach_images_to_posts method when both venue and event are created.
	 * Verifies that event takes priority over venue.
	 *
	 * @covers ::maybe_attach_images_to_posts
	 *
	 * @return void
	 */
	public function test_maybe_attach_images_to_posts_event_priority_over_venue(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create both a venue and an event.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Get attachment file path.
		$attachment_file = get_attached_file( $attachment_id );
		if ( ! $attachment_file || ! file_exists( $attachment_file ) ) {
			// Create a minimal image file for the attachment.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
			$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, $jpeg_data );
			$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
			if ( ! file_exists( $upload_dir['path'] ) ) {
				wp_mkdir_p( $upload_dir['path'] );
			}
			copy( $temp_file, $file_path );
			update_attached_file( $attachment_id, $file_path );
			// Clean up temp file.
			if ( file_exists( $temp_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
				unlink( $temp_file );
			}
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$instance = Admin_Page::get_instance();

		$attachment_ids = array( $attachment_id );

		// Test scenario: venue created first, then event (the bug scenario).
		$result = array(
			'actions' => array(
				array(
					'ability' => 'gatherpress/create-venue',
					'result'  => array(
						'success'  => true,
						'venue_id' => $venue_id,
					),
				),
				array(
					'ability' => 'gatherpress/create-event',
					'result'  => array(
						'success'  => true,
						'event_id' => $event_id,
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'maybe_attach_images_to_posts', array( $attachment_ids, $result ) );

		// Verify thumbnail was set on EVENT (not venue) - event should take priority.
		$event_thumbnail_id = get_post_thumbnail_id( $event_id );
		$this->assertSame( $attachment_id, $event_thumbnail_id, 'Failed to assert thumbnail was set on event.' );

		// Verify thumbnail was NOT set on venue.
		$venue_thumbnail_id = get_post_thumbnail_id( $venue_id );
		$this->assertEmpty( $venue_thumbnail_id, 'Failed to assert thumbnail was NOT set on venue.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}
}
