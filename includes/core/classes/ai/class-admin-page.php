<?php
/**
 * Handles the AI Assistant admin page.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Admin_Page.
 *
 * Manages the AI Assistant admin interface.
 *
 * @since 1.0.0
 */
class Admin_Page {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Image handler instance (for dependency injection in tests).
	 *
	 * @var Image_Handler|null
	 */
	protected $image_handler = null;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for admin page.
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_gatherpress_ai_process_prompt', array( $this, 'process_prompt_ajax' ) );
	}

	/**
	 * Add admin page to WordPress menu.
	 *
	 * Only adds the AI Assistant page if the Abilities API is available.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		// Only show AI Assistant if Abilities API is available.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=gatherpress_event',
			__( 'AI Assistant', 'gatherpress' ),
			__( 'AI Assistant', 'gatherpress' ),
			'edit_posts',
			'gatherpress-ai-assistant',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue scripts and styles for admin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ): void {
		if ( 'gatherpress_event_page_gatherpress-ai-assistant' !== $hook ) {
			return;
		}

		$style_asset  = $this->get_asset_data( 'ai-assistant-style' );
		$script_asset = $this->get_asset_data( 'ai-assistant' );

		wp_enqueue_style(
			'gatherpress-ai-assistant',
			GATHERPRESS_CORE_URL . 'build/style-ai-assistant-style.css',
			$style_asset['dependencies'] ?? array(),
			$style_asset['version'] ?? ''
		);

		wp_enqueue_script(
			'gatherpress-ai-assistant',
			GATHERPRESS_CORE_URL . 'build/ai-assistant.js',
			array_merge( array( 'jquery' ), $script_asset['dependencies'] ?? array() ),
			$script_asset['version'] ?? '',
			true
		);

		wp_localize_script(
			'gatherpress-ai-assistant',
			'gatherpressAI',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gatherpress_ai_nonce' ),
			)
		);
	}

	/**
	 * Render the AI Assistant admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! $this->has_api_key() ) {
			?>
			<div class="wrap gp-ai-assistant">
				<h1><?php echo esc_html__( 'GatherPress AI Assistant', 'gatherpress' ); ?></h1>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'API Key Required', 'gatherpress' ); ?></strong><br>
						<?php
						esc_html_e(
							'Please configure your AI API credentials to use the AI Assistant.',
							'gatherpress'
						);
						?>
					</p>
					<p>
						<?php
						$settings_url = admin_url( 'options-general.php?page=wp-ai-client' );
						?>
						<a href="<?php echo esc_url( $settings_url ); ?>" 
							class="button button-primary">
							<?php esc_html_e( 'Configure API Key →', 'gatherpress' ); ?>
						</a>
					</p>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<div class="wrap gp-ai-assistant">
			<h1><?php echo esc_html__( 'GatherPress AI Assistant', 'gatherpress' ); ?></h1>
			
			<div class="gp-ai-container">
				<div class="gp-ai-intro">
					<h2><?php esc_html_e( 'Create and Manage Events with AI', 'gatherpress' ); ?></h2>
					<p>
						<?php
						echo esc_html(
							__( 'Tell me what you want to do in plain English, ', 'gatherpress' ) .
							__( 'and I\'ll help you create and manage your GatherPress events.', 'gatherpress' )
						);
						?>
					</p>
					
					<div class="gp-ai-examples">
						<p><strong><?php esc_html_e( 'Example prompts:', 'gatherpress' ); ?></strong></p>
						<ul>
							<li>"Create a book club event on the 3rd Tuesday of each month "
								. "for 6 months at Downtown Library, 7pm"</li>
							<li>"Change all Book Club events from 7pm to 8pm"</li>
							<li>"Create a 5-day conference from May 1-5 at the Convention Center"</li>
							<li>"List all my venues"</li>
						</ul>
					</div>

				</div>

				<div class="gp-ai-chat">
					<div id="gp-ai-messages" class="gp-ai-messages">
						<!-- Messages will appear here -->
					</div>
					
					<div class="gp-ai-input-container">
						<div style="margin-bottom: 10px;">
							<label for="gp-ai-image-upload" style="display: block; margin-bottom: 5px;">
								<strong><?php esc_html_e( 'Upload Images:', 'gatherpress' ); ?></strong>
							</label>
							<input
								type="file"
								id="gp-ai-image-upload"
								name="images"
								accept="image/jpeg,image/png,image/gif,image/webp"
								multiple
								style="margin-bottom: 5px;"
							/>
							<small style="display: block; color: #666;">
								<?php
								esc_html_e( 'Select one or more images to upload with your prompt', 'gatherpress' );
								?>
							</small>
						</div>
						<textarea 
							id="gp-ai-prompt" 
							class="gp-ai-prompt" 
							placeholder="<?php esc_attr_e( 'What would you like me to do?', 'gatherpress' ); ?>"
							rows="3"
						></textarea>
						<button type="button" id="gp-ai-submit" class="button button-primary button-large">
							<?php esc_html_e( 'Send', 'gatherpress' ); ?>
						</button>
					</div>
				</div>

				<div class="gp-ai-status" id="gp-ai-status" style="display:none;">
					<p class="gp-ai-processing">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Processing...', 'gatherpress' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request to process AI prompt.
	 *
	 * @return void
	 */
	public function process_prompt_ajax(): void {
		check_ajax_referer( 'gatherpress_ai_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$handler = new AI_Handler();

		// Handle get_state request.
		$get_state = isset( $_POST['get_state'] )
			&& 'true' === sanitize_text_field( wp_unslash( $_POST['get_state'] ) );
		if ( $get_state ) {
			$state = $handler->get_conversation_state_metadata();
			wp_send_json_success( array( 'state' => $state ) );
			// wp_send_json_success() terminates execution.
		}

		// Handle reset request.
		$reset = isset( $_POST['reset'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['reset'] ) );
		if ( $reset ) {
			$state = $handler->reset_conversation_state();
			wp_send_json_success( array( 'state' => $state ) );
			// wp_send_json_success() terminates execution.
		}

		// Handle prompt request.
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';

		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => 'Prompt is required' ) );
		}

		// Handle image uploads if present.
		$attachment_ids = $this->handle_image_uploads();

		// Process with AI handler (wp-ai-client).
		try {
			$result = $handler->process_prompt( $prompt, $attachment_ids );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Error processing prompt: ' . $e->getMessage(),
				)
			);
			return;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		// Attach images to events or venues if images were uploaded and an event/venue was created/updated.
		$this->maybe_attach_images_to_posts( $attachment_ids, $result );

		// Add gentle reminder if event or venue was created/updated without an image.
		$this->maybe_add_image_reminder( $attachment_ids, $result );

		// Add attachment IDs to response.
		if ( ! empty( $attachment_ids ) ) {
			$result['attachment_ids'] = $attachment_ids;
		}

		// Debug: Add image URL info to response for debugging.
		if ( ! empty( $attachment_ids ) ) {
			$debug_urls = array();
			foreach ( $attachment_ids as $attachment_id ) {
				$url          = wp_get_attachment_url( $attachment_id );
				$debug_urls[] = array(
					'attachment_id' => $attachment_id,
					'url'           => $url,
				);
			}
			$result['debug_image_urls'] = $debug_urls;
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handle image uploads from $_FILES.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int> Array of attachment IDs.
	 */
	private function handle_image_uploads(): array {
		$attachment_ids = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in process_prompt_ajax() before this method is called.
		if ( empty( $_FILES['images'] ) ) {
			return $attachment_ids;
		}

		// Use injected image handler or create new one (allows mocking in tests).
		$image_handler = $this->image_handler ?? new Image_Handler();

		// Handle multiple files (when input has name="images[]") or single file.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in parent method. $_FILES array structure is validated, file data is handled by WordPress functions.
		$files = $_FILES['images'];

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES array structure is validated, file data is handled by WordPress functions.
		if ( is_array( $files['name'] ) ) {
			// Multiple files.
			$file_count = count( $files['name'] );
			for ( $i = 0; $i < $file_count; $i++ ) {
				$file = array(
					'name'     => $files['name'][ $i ],
					'type'     => $files['type'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				);

				$attachment_id = $image_handler->upload_to_media_library( $file );
				if ( ! is_wp_error( $attachment_id ) ) {
					$attachment_ids[] = $attachment_id;
				}
			}
		} else {
			// Single file.
			$attachment_id = $image_handler->upload_to_media_library( $files );
			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		return $attachment_ids;
	}

	/**
	 * Attach images to events or venues if images were uploaded and an event/venue was created/updated.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @param array      $result         Result from AI handler.
	 * @return void
	 */
	private function maybe_attach_images_to_posts( array $attachment_ids, array &$result ): void {
		if ( empty( $attachment_ids ) || ! isset( $result['actions'] ) || ! is_array( $result['actions'] ) ) {
			return;
		}

		// Validate and get the first valid image attachment.
		$first_attachment_id = intval( $attachment_ids[0] );
		if ( ! $first_attachment_id ) {
			return;
		}

		// Verify attachment exists and is an image.
		$attachment = get_post( $first_attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $first_attachment_id ) ) {
			return;
		}

		foreach ( $result['actions'] as $action ) {
			if ( ! isset( $action['ability'] ) || ! isset( $action['result'] ) ) {
				continue;
			}

			$action_result = $action['result'];
			if ( ! isset( $action_result['success'] ) || true !== $action_result['success'] ) {
				continue;
			}

			$thumbnail_result = false;

			// Check if this is a create or update event action.
			$event_abilities = array( 'gatherpress/create-event', 'gatherpress/update-event' );
			if ( in_array( $action['ability'], $event_abilities, true ) ) {
				if ( isset( $action_result['event_id'] ) ) {
					$event_id         = intval( $action_result['event_id'] );
					if ( $event_id ) {
						$thumbnail_result = set_post_thumbnail( $event_id, $first_attachment_id );
					}
				}
			}

			// Check if this is a create or update venue action.
			$venue_abilities = array( 'gatherpress/create-venue', 'gatherpress/update-venue' );
			if ( in_array( $action['ability'], $venue_abilities, true ) ) {
				if ( isset( $action_result['venue_id'] ) ) {
					$venue_id         = intval( $action_result['venue_id'] );
					if ( $venue_id ) {
						$thumbnail_result = set_post_thumbnail( $venue_id, $first_attachment_id );
					}
				}
			}

			// Only attach to the first event/venue if multiple were created/updated.
			if ( $thumbnail_result ) {
				break;
			}
		}
	}

	/**
	 * Add gentle reminder if event or venue was created/updated without an image.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @param array      $result         Result from AI handler (passed by reference).
	 * @return void
	 */
	private function maybe_add_image_reminder( array $attachment_ids, array &$result ): void {
		if ( ! empty( $attachment_ids ) || ! isset( $result['actions'] ) || ! is_array( $result['actions'] ) ) {
			return;
		}

		$event_or_venue_created_or_updated = false;
		$is_venue                          = false;
		foreach ( $result['actions'] as $action ) {
			if ( ! isset( $action['ability'] ) || ! isset( $action['result'] ) ) {
				continue;
			}

			$action_result = $action['result'];
			if ( ! isset( $action_result['success'] ) || true !== $action_result['success'] ) {
				continue;
			}

			// Check if this is a create or update event action.
			$event_abilities = array( 'gatherpress/create-event', 'gatherpress/update-event' );
			if ( in_array( $action['ability'], $event_abilities, true ) ) {
				if ( isset( $action_result['event_id'] ) ) {
					$event_or_venue_created_or_updated = true;
					$is_venue                          = false;
					break;
				}
			}

			// Check if this is a create or update venue action.
			$venue_abilities = array( 'gatherpress/create-venue', 'gatherpress/update-venue' );
			if ( in_array( $action['ability'], $venue_abilities, true ) ) {
				if ( isset( $action_result['venue_id'] ) ) {
					$event_or_venue_created_or_updated = true;
					$is_venue                          = true;
					break;
				}
			}
		}

		// Append gentle reminder to response if event or venue was created/updated without image.
		if ( $event_or_venue_created_or_updated && isset( $result['response'] ) ) {
			if ( $is_venue ) {
				$reminder_text = __(
					'💡 Tip: Consider adding an image to make your venue more engaging!',
					'gatherpress'
				);
			} else {
				$reminder_text = __(
					'💡 Tip: Consider adding an image to make your event more engaging!',
					'gatherpress'
				);
			}
			$reminder           = "\n\n" . $reminder_text;
			$result['response'] = $result['response'] . $reminder;
		}
	}

	/**
	 * Check if API key is configured.
	 *
	 * @return bool
	 */
	private function has_api_key(): bool {
		// Check wp-ai-client credentials.
		$credentials = get_option( 'wp_ai_client_provider_credentials', array() );

		if ( ! is_array( $credentials ) ) {
			return false;
		}

		foreach ( $credentials as $api_key ) {
			if ( ! empty( $api_key ) && is_string( $api_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get asset data from build directory.
	 *
	 * @param string $asset Asset name without extension.
	 * @return array Asset data including dependencies and version.
	 */
	private function get_asset_data( string $asset ): array {
		$path = GATHERPRESS_CORE_PATH . '/build/' . $asset . '.asset.php';
		if ( file_exists( $path ) ) {
			return (array) require $path;
		}

		return array(
			'dependencies' => array(),
			'version'      => defined( 'GATHERPRESS_VERSION' ) ? GATHERPRESS_VERSION : '',
		);
	}
}

