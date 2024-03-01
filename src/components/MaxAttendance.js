/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

/**
 * MaxAttendance component.
 *
 * This component renders a number control that allows setting the maximum attendance limit for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the control is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A number control for setting the maximum attendance limit.
 */
const MaxAttendance = () => {
    const { editPost, unlockPostSaving } = useDispatch( 'core/editor' ); // Make sure to correct typo from `unLockPostSaving` to `unlockPostSaving`
    const isNewEvent = useSelect( (select) => { 
        return select( 'core/editor' ).isCleanNewPost();
    }, [] );

    let defaultMaxAttendance = useSelect( ( select ) => { // Correct typo from `defaultMaxAttendane` to `defaultMaxAttendance`
        return select( 'core/editor' ).getEditedPostAttribute( 'meta' ).max_attendance;
    }, []);

    if (isNewEvent) {
        defaultMaxAttendance = getFromGlobal( 'settings.maxAttendance' );
    }

    const [maxAttendance, setMaxAttendance] = useState( defaultMaxAttendance );

    const updateMaxAttendance = useCallback(
        (value) => {
            const meta = { max_attendance: Number( value ) };

            setMaxAttendance( value );
            editPost( { meta } );
            unlockPostSaving(); // Call `unlockPostSaving` here to unlock the save button after updating the meta
        },
        [editPost, unlockPostSaving]
    );

    useEffect( () => {
        if ( isNewEvent && defaultMaxAttendance !==0 ) {
            updateMaxAttendance( defaultMaxAttendance );
        }
    }, [isNewEvent, defaultMaxAttendance, updateMaxAttendance]);

    return (
        <NumberControl 
            label={__( 'Maximum Attending Limit', 'gatherpress')}
            value={ maxAttendance }
            min={0}
            onChange={ (value) => {
                updateMaxAttendance( value );
            } }
        />
    );
};

export default MaxAttendance;