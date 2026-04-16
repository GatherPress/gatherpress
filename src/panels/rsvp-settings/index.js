/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies.
 */
import { isEventPostType, isOpenRsvpEnabled, isPerEventRsvpMode } from '../../helpers/event';
import { getFromSettings } from '../../helpers/editor-settings';
import AnonymousRsvpPanel from './anonymous-rsvp';
import EnableOpenRsvpPanel from './enable-open-rsvp';
import EnableRsvpPanel from './enable-rsvp';
import GuestLimitPanel from './guest-limit';
import MaxAttendanceLimitPanel from './max-attendance-limit';
import { RsvpPluginDocumentSettings } from './slot';

/**
 * A settings panel for RSVP-specific settings in the block editor.
 *
 * This component renders a `PluginDocumentSettingPanel` containing various
 * subpanels for configuring RSVP-related settings, such as guest limits,
 * attendance limits, and anonymous RSVP options.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element | null} The JSX element for the RsvpSettings panel if
 * the current post type is an event; otherwise, returns null.
 */
const RsvpSettings = () => {
	// Only show per-event RSVP toggle when the admin has set rsvp_mode to per_event.
	const rsvpMode = getFromSettings( 'rsvpMode' ) ?? 'all_on';
	const enableOpenRsvp = getFromSettings( 'enableOpenRsvp' ) ?? true;

	return (
		isEventPostType() && 'disabled' !== rsvpMode && (
			<PluginDocumentSettingPanel
				name="gatherpress-rsvp-settings"
				title={ __( 'RSVP settings', 'gatherpress' ) }
				className="gatherpress-rsvp-settings"
			>
				{ /* Extendable entry point for "RSVP Settings" panel. */ }
				<RsvpPluginDocumentSettings.Slot />

				<VStack spacing={ 4 }>
					{ isPerEventRsvpMode( rsvpMode ) && <EnableRsvpPanel /> }
					{ isOpenRsvpEnabled( enableOpenRsvp ) && (
						<EnableOpenRsvpPanel />
					) }
					<GuestLimitPanel />
					<MaxAttendanceLimitPanel />
					<AnonymousRsvpPanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

/**
 * Registers the 'gatherpress-rsvp-settings' plugin.
 *
 * This function registers a custom plugin named 'gatherpress-rsvp-settings' and
 * associates it with the `RsvpSettings` component for rendering.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerPlugin( 'gatherpress-rsvp-settings', {
	render: RsvpSettings,
} );
