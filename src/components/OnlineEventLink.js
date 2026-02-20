/**
 * WordPress dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster, Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';
import { TAX_VENUE } from '../helpers/namespace';

/**
 * OnlineEventLink component for GatherPress.
 *
 * This component provides a toggle to mark an event as online, and when enabled,
 * shows a TextControl input for adding the online event link. It updates the post
 * meta, manages the online-event taxonomy term, and broadcasts changes to other components.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const OnlineEventLink = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	// Get the online event link from meta.
	const onlineEventLinkMetaData = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				.gatherpress_online_event_link,
	);

	// Get current venue taxonomy terms.
	const venueTermIds = useSelect( ( select ) =>
		select( 'core/editor' ).getEditedPostAttribute( TAX_VENUE ),
	);

	// Get the online-event term to find its ID.
	const onlineEventTerm = useSelect( ( select ) => {
		const terms = select( 'core' ).getEntityRecords( 'taxonomy', TAX_VENUE, {
			slug: 'online-event',
			per_page: 1,
		} );
		return terms?.[ 0 ] || null;
	}, [] );

	// Check if online-event term is currently assigned.
	// Term IDs may be strings or numbers depending on source, so compare as strings.
	const hasOnlineEventTerm = ( () => {
		if ( ! onlineEventTerm || ! venueTermIds ) {
			return false;
		}
		const termIds = Array.isArray( venueTermIds )
			? venueTermIds
			: [ venueTermIds ];
		const onlineTermId = String( onlineEventTerm.id );
		return termIds.some( ( id ) => String( id ) === onlineTermId );
	} )();

	const [ onlineEventLink, setOnlineEventLink ] = useState(
		onlineEventLinkMetaData,
	);
	const [ isOnlineEvent, setIsOnlineEvent ] = useState( false );

	// Sync toggle state with term presence.
	useEffect( () => {
		setIsOnlineEvent( hasOnlineEventTerm );
	}, [ hasOnlineEventTerm ] );

	// Sync link state with meta.
	useEffect( () => {
		setOnlineEventLink( onlineEventLinkMetaData );
	}, [ onlineEventLinkMetaData ] );

	const updateEventLink = ( value ) => {
		const meta = { gatherpress_online_event_link: value };

		editPost( { meta } );
		setOnlineEventLink( value );
		Broadcaster(
			{ setOnlineEventLink: value },
			getFromGlobal( 'eventDetails.postId' ),
		);
		unlockPostSaving();
	};

	const updateOnlineEventTerm = ( shouldAdd ) => {
		if ( ! onlineEventTerm ) {
			return;
		}

		let currentTerms = [];
		if ( Array.isArray( venueTermIds ) ) {
			currentTerms = [ ...venueTermIds ];
		} else if ( venueTermIds ) {
			currentTerms = [ venueTermIds ];
		}

		// Use string for consistent comparison, but store as number for API.
		const termId = onlineEventTerm.id;
		const termIdStr = String( termId );
		const hasTermAlready = currentTerms.some(
			( id ) => String( id ) === termIdStr
		);

		let newTerms;
		if ( shouldAdd ) {
			// Add the online-event term if not present.
			if ( ! hasTermAlready ) {
				newTerms = [ ...currentTerms, termId ];
			} else {
				newTerms = currentTerms;
			}
		} else {
			// Remove the online-event term.
			newTerms = currentTerms.filter(
				( id ) => String( id ) !== termIdStr
			);
		}

		editPost( { [ TAX_VENUE ]: newTerms } );
		unlockPostSaving();
	};

	const handleToggleChange = ( value ) => {
		setIsOnlineEvent( value );
		updateOnlineEventTerm( value );
		if ( ! value ) {
			// Clear the link when toggling off.
			updateEventLink( '' );
		}
	};

	Listener( { setOnlineEventLink }, getFromGlobal( 'eventDetails.postId' ) );

	return (
		<>
			<ToggleControl
				label={ __( 'This is an online event', 'gatherpress' ) }
				checked={ isOnlineEvent }
				onChange={ handleToggleChange }
			/>
			{ isOnlineEvent && (
				<TextControl
					label={ __( 'Online event link', 'gatherpress' ) }
					value={ onlineEventLink }
					placeholder={ __( 'Add link to online event', 'gatherpress' ) }
					onChange={ ( value ) => {
						updateEventLink( value );
					} }
				/>
			) }
		</>
	);
};

export default OnlineEventLink;
