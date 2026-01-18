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
import { getFromGlobal } from '../helpers/globals';

/**
 * AnonymousRsvp component.
 *
 * This component renders a checkbox control that allows toggling the anonymous RSVP feature for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the checkbox is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling anonymous RSVPs.
 */
const AnonymousRsvp = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const isNewEvent = useSelect( ( select ) => {
		return select( 'core/editor' ).isCleanNewPost();
	}, [] );

	let defaultAnonymousRsvp = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		const rawValue = meta?.gatherpress_enable_anonymous_rsvp;

		// Convert meta value to boolean.
		return Boolean( rawValue );
	}, [] );

	if ( isNewEvent ) {
		defaultAnonymousRsvp = getFromGlobal( 'settings.enableAnonymousRsvp' );
	}

	const [ anonymousRsvp, setAnonymousRsvp ] = useState( defaultAnonymousRsvp );

	const updateAnonymousRsvp = useCallback(
		( value ) => {
			// Save as boolean to match meta registration.
			const meta = { gatherpress_enable_anonymous_rsvp: Boolean( value ) };

			setAnonymousRsvp( value );
			editPost( { meta } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	useEffect( () => {
		if ( isNewEvent && defaultAnonymousRsvp ) {
			updateAnonymousRsvp( defaultAnonymousRsvp );
		}
	}, [ isNewEvent, defaultAnonymousRsvp, updateAnonymousRsvp ] );

	return (
		<CheckboxControl
			label={ __( 'Enable Anonymous RSVP', 'gatherpress' ) }
			checked={ anonymousRsvp }
			onChange={ ( value ) => {
				updateAnonymousRsvp( value );
			} }
		/>
	);
};

export default AnonymousRsvp;
