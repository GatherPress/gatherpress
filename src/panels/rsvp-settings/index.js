/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { isOpenRsvpEnabled, isPerEventRsvpMode, usePostTypeSupports } from '../../helpers/event';
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
 * attendance limits, and anonymous RSVP options. The panel is gated on
 * `gatherpress-rsvp` post type support so that event-date-only post types
 * (e.g. theater productions tagged with a premiere date) do not surface RSVP
 * controls that have no underlying meta storage.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element | null} The JSX element for the RsvpSettings panel if
 * the current post type supports RSVP; otherwise, returns null.
 */
const RsvpSettings = () => {
	// Only show per-event RSVP toggle when the admin has set rsvp_mode to per_event.
	const rsvpMode = getFromSettings( 'rsvpMode' ) ?? 'all_on';
	const enableOpenRsvp = getFromSettings( 'enableOpenRsvp' ) ?? true;
	const supportsRsvp = usePostTypeSupports( 'gatherpress-rsvp' );

	return (
		supportsRsvp && 'disabled' !== rsvpMode && (
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
