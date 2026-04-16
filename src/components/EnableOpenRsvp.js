/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * EnableOpenRsvp component.
 *
 * Renders a checkbox that toggles Open RSVP for a specific event.
 * Open RSVP allows visitors without a site account to submit an RSVP
 * using their name and email address. This per-event toggle is only
 * visible when the sitewide Open RSVP setting is enabled.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling Open RSVP per event.
 */
const EnableOpenRsvp = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const isNewEvent = useSelect( ( select ) => {
		return select( 'core/editor' ).isCleanNewPost();
	}, [] );

	const metaDefault = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		const rawValue = meta?.gatherpress_enable_open_rsvp;

		// Stored as integer (0/1); undefined/null means not yet set — default to enabled.
		return rawValue === undefined || null === rawValue ? true : 0 !== rawValue;
	}, [] );

	// New events default to enabled; existing events use the per-event meta value.
	const defaultEnableOpenRsvp = isNewEvent ? true : metaDefault;

	const [ enableOpenRsvp, setEnableOpenRsvp ] = useState(
		defaultEnableOpenRsvp,
	);

	const updateEnableOpenRsvp = useCallback(
		( value ) => {
			// Save as integer (1/0) — WordPress stores boolean false as '' which is ambiguous.
			const meta = { gatherpress_enable_open_rsvp: value ? 1 : 0 };

			setEnableOpenRsvp( value );
			editPost( { meta } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	useEffect( () => {
		if ( isNewEvent ) {
			updateEnableOpenRsvp( defaultEnableOpenRsvp );
		}
	}, [ isNewEvent, defaultEnableOpenRsvp, updateEnableOpenRsvp ] );

	return (
		<CheckboxControl
			label={ __( 'Enable Open RSVP', 'gatherpress' ) }
			help={ __(
				'Requires the RSVP Form block. Visitors without an account can RSVP using their name and email address.',
				'gatherpress',
			) }
			checked={ enableOpenRsvp }
			onChange={ ( value ) => {
				updateEnableOpenRsvp( value );
			} }
		/>
	);
};

export default EnableOpenRsvp;
