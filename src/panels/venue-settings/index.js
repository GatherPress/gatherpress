/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { isVenuePostType } from '../../helpers/venue';
import { usePostTypeLabel } from '../../helpers/event';
import VenueInformationPanel from './venue-information';
import { VenuePluginDocumentSettings } from './slot';
import VenuePluginFill from './fill';

/**
 * VenueSettings Component
 *
 * This component represents a panel in the Block Editor for venue settings.
 * It includes the VenueInformationPanel component to manage and display venue details.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueSettings.
 */
const VenueSettings = () => {
	const currentPostType = useSelect(
		( s ) => s( 'core/editor' )?.getCurrentPostType(),
		[]
	);

	// Read the singular label so the panel title reflects what the post type
	// is actually called — a custom venue post type with
	// `singular_name => 'Location'` shows "Location settings" without any
	// extra wiring (#1612).
	const singularLabel = usePostTypeLabel(
		'singular_name',
		currentPostType,
		__( 'Venue', 'gatherpress' )
	);

	return (
		isVenuePostType() && (
			<PluginDocumentSettingPanel
				name="gatherpress-venue-settings"
				title={ sprintf(
					/* translators: %s: Singular post type label, e.g. "Venue". */
					__( '%s settings', 'gatherpress' ),
					singularLabel
				) }
				className="gatherpress-venue-settings"
			>
				{ /* Extendable entry point for "Venue Settings" panel. */ }
				<VenuePluginDocumentSettings.Slot />

				<VStack spacing={ 6 }>
					<VenueInformationPanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

/**
 * Register Venue Settings Plugin
 *
 * This function registers the VenueSettings component as a plugin to be rendered in the Block Editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerPlugin( 'gatherpress-venue-settings', {
	render: VenueSettings,
} );

registerPlugin( 'gatherpress-venue-settings-at-events', {
	render: VenuePluginFill,
} );
