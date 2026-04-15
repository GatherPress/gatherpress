/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

/**
 * Get a GatherPress plugin setting from the block editor settings.
 *
 * Retrieves values exposed by the Settings::add_editor_settings() PHP method
 * via the block_editor_settings_all filter. Available only in the block editor context.
 *
 * This function must NOT be imported in view scripts (viewScriptModule entries)
 * because @wordpress/data is not available as a script module.
 *
 * @since 1.0.0
 *
 * @param {string} key - The camelCase setting key (e.g., 'dateFormat', 'mapPlatform').
 * @return {*} The setting value, or undefined if not available.
 */
export function getFromSettings( key ) {
	return select( 'core/editor' )?.getEditorSettings?.()?.gatherpress
		?.settings?.[ key ];
}
