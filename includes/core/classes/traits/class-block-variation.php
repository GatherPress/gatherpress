<?php
/**
 * Common class that handles everything a block-variation needs in GatherPress.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Common class that handles registering and enqueuing of assets.
 *
 * @since 1.0.0
 */
trait Block_Variation {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Slug of the asset
	 *
	 * @var string
	 */
	public string $asset;

	/**
	 * Set up hooks for registering & enqueuing of assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_and_enqueue_assets(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register all assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		\array_map(
			array( $this, 'register_asset' ),
			array( $this->get_foldername_from_classname() )
		);
	}


	/**
	 * Enqueue all assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		\array_map(
			array( $this, 'enqueue_asset' ),
			array( $this->get_foldername_from_classname() )
		);
	}


	/**
	 * Register a new script and sets translated strings for the script.
	 *
	 * @throws \Error If build-files doesn't exist errors out in local environments and writes to error_log otherwise.
	 *
	 * @param  string $asset Slug of the block to register scripts and translations for.
	 *
	 * @return void
	 */
	protected function register_asset( string $asset ): void {

		$asset_path        = sprintf( '%1$s/build/variations/%2$s', GATHERPRESS_CORE_PATH, $asset );
		$script_asset_path = sprintf( '%1$s/index.asset.php', $asset_path );
		$style_asset_path  = sprintf( '%1$s/index.css', $asset_path );

		if ( ! \file_exists( $script_asset_path ) ) {
			$error_message = "You need to run `npm start` or `npm run build` for the '$asset' block-asset first.";
			if ( \in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
				throw new \Error( esc_html( $error_message ) );
			} else {
				// Should write to the \error_log( $error_message ); if possible.
				return;
			}
		}

		$index_js     = "build/variations/$asset/index.js";
		$script_asset = require $script_asset_path;
		\wp_register_script(
			"gatherpress--$asset",
			plugins_url( $index_js, GATHERPRESS_CORE_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			"gatherpress--$asset",
			'gatherpress'
		);

		if ( \file_exists( $style_asset_path ) ) {
			$index_css = "build/variations/$asset/index.css";
			\wp_register_style(
				"gatherpress--$asset",
				plugins_url( $index_css, GATHERPRESS_CORE_FILE ),
				array( 'global-styles' ),
				$script_asset['version'],
				'screen'
			);
		}
	}


	/**
	 * Enqueue a script.
	 *
	 * @param  string $asset Slug of the block to load the frontend scripts for.
	 *
	 * @return void
	 */
	protected function enqueue_asset( string $asset ): void {
		wp_enqueue_script( "gatherpress--$asset" );

		if ( wp_style_is( $asset, 'registered' ) ) {
			wp_enqueue_style( "gatherpress--$asset" );
		}
	}


	/**
	 * Get foldername from classname.
	 *
	 * @todo maybe better in the Utility class?
	 *
	 * @param  string $classname Class to get the foldername from.
	 *
	 * @return string String with name of a folder.
	 */
	protected static function get_foldername_from_classname( string $classname = __CLASS__ ): string {
		$current_class = explode(
			'\\',
			$classname
		);
		return strtolower(
			str_replace(
				'_',
				'-',
				array_pop(
					$current_class
				)
			)
		);
	}
}
