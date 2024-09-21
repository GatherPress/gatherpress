<?php
/**
 * ...
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * 
 *
 * @since 1.0.0
 */
trait Block_Variation {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	public string $asset;

	protected function register_and_enqueue_assets(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * 
	 *
	 * @return void
	 */
	public function register_assets(): void {
		\array_map(
			array( $this, 'register_asset' ),
			\array_merge(
				// get_editor_assets(),
				[
					$this->get_foldername_from_classname(),
				]
			)
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

	$script_asset_path = sprintf( '%1$s/build/variations/%2$s/index.asset.php', GATHERPRESS_CORE_PATH, $asset );
	// wp_die($script_asset_path);
	if ( ! \file_exists( $script_asset_path ) ) {
		$error_message = "You need to run `npm start` or `npm run build` for the '$asset' block-asset first.";
		if ( \in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) ) {
			throw new \Error( esc_html( $error_message ) );
		} else {
			// Should write to the \error_log( $error_message ); if possible.
			return;
		}
	}

	$index_js     = "build/$asset/index.js";
	$script_asset = require $script_asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
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

	// $index_css = "build/$asset/$asset.css";
	// \wp_register_style(
	// 	"gatherpress--$asset",
	// 	plugins_url( $index_css, __FILE__ ),
	// 	[ 'wp-block-buttons','wp-block-button','global-styles' ],
	// 	time(),
	// 	'screen'
	// );

}


/**
 * Enqueue all scripts.
 *
 * @return void
 */
protected function enqueue_assets(): void {
	\array_map(
		array( $this, 'enqueue_asset' ),
		\array_merge(
			// get_editor_assets(),
			[
				$this->get_foldername_from_classname(),
			]
		)
	);
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
	wp_enqueue_style( "gatherpress--$asset" );
}


	/**
	 * Get foldername from classname.
	 * 
	 * @todo maybe better in the Utility class?
	 *
	 * @param  string $classname
	 *
	 * @return string
	 */
	protected static function get_foldername_from_classname( string $classname = __CLASS__ ) : string {
		$current_class = explode(
			'\\',
			$classname
		);
		return strtolower( str_replace(
			'_',
			'-',
			array_pop(
				$current_class
			)
		));
	}
}
