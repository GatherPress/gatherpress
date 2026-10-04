/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getFromSettings } from '../helpers/editor-settings';

/**
 * Capacity component.
 *
 * This component renders a number control that allows setting an event's capacity.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the control is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A number control for setting an event's capacity.
 */
const Capacity = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const isNewEvent = useSelect( ( select ) => {
		return select( 'core/editor' ).isCleanNewPost();
	}, [] );

	let defaultCapacity = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )
			.gatherpress_capacity;
	}, [] );

	if ( isNewEvent ) {
		defaultCapacity = getFromSettings( 'capacity' );
	}

	if ( false === defaultCapacity ) {
		defaultCapacity = 0;
	}

	const [ capacity, setCapacity ] = useState( defaultCapacity );

	const updateCapacity = useCallback(
		( value ) => {
			const meta = { gatherpress_capacity: Number( value ) };

			setCapacity( value );
			editPost( { meta } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	useEffect( () => {
		if ( isNewEvent && 0 !== defaultCapacity ) {
			updateCapacity( defaultCapacity );
		}
	}, [ isNewEvent, defaultCapacity, updateCapacity ] );

	return (
		<NumberControl
			__next40pxDefaultSize
			label={ __( 'Capacity', 'gatherpress' ) }
			value={ capacity }
			min={ 0 }
			help={ __(
				'Total number of people allowed at the event. A value of 0 indicates no limit.',
				'gatherpress',
			) }
			onChange={ ( value ) => {
				updateCapacity( value );
			} }
		/>
	);
};

export default Capacity;
