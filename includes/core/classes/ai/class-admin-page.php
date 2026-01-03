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
						<textarea 
							id="gp-ai-prompt" 
							class="gp-ai-prompt" 
							placeholder="<?php esc_attr_e( 'What would you like me to do?', 'gatherpress' ); ?>"
							rows="3"
						></textarea>
						<button id="gp-ai-submit" class="button button-primary button-large">
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

		// Process with AI handler (wp-ai-client).
		$result = $handler->process_prompt( $prompt );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
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

