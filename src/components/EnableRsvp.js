/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromSettings } from '../helpers/editor-settings';

/**
 * EnableRsvp component.
 *
 * This component renders a checkbox control that toggles RSVP for a specific event.
 * When creating a new event, the default state is determined by the global setting.
 * For existing events, the current per-event setting is used.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling RSVP.
 */
const EnableRsvp = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const isNewEvent = useSelect( ( select ) => {
		return select( 'core/editor' ).isCleanNewPost();
	}, [] );

	let defaultEnableRsvp = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		const rawValue = meta?.gatherpress_enable_rsvp;

		// Stored as integer (0/1); undefined/null means not yet set - default to enabled.
		return rawValue === undefined || null === rawValue ? true : 0 !== rawValue;
	}, [] );

	if ( isNewEvent ) {
		// Default based on rsvp_mode: per_event_off defaults to disabled, per_event_on defaults to enabled.
		defaultEnableRsvp = 'per_event_off' !== ( getFromSettings( 'rsvpMode' ) ?? 'all_on' );
	}

	const [ enableRsvp, setEnableRsvp ] = useState( defaultEnableRsvp );

	const updateEnableRsvp = useCallback(
		( value ) => {
			// Save as integer (1/0) - WordPress stores boolean false as '' which is ambiguous.
			const meta = { gatherpress_enable_rsvp: value ? 1 : 0 };

			setEnableRsvp( value );
			editPost( { meta } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	useEffect( () => {
		if ( isNewEvent ) {
			updateEnableRsvp( defaultEnableRsvp );
		}
	}, [ isNewEvent, defaultEnableRsvp, updateEnableRsvp ] );

	return (
		<CheckboxControl
			label={ __( 'Enable RSVP', 'gatherpress' ) }
			checked={ enableRsvp }
			onChange={ ( value ) => {
				updateEnableRsvp( value );
			} }
		/>
	);
};

export default EnableRsvp;
