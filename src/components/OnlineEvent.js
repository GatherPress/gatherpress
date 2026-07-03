/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { usePostTypeLabel } from '../helpers/editor';
import { getVenuePostType, getVenueTaxonomy } from '../helpers/venue';

/**
 * OnlineEvent component for GatherPress.
 *
 * This component provides a toggle to mark an event as online, and when enabled,
 * shows a TextControl input for adding the online event link. It updates the post
 * meta and manages the online-event taxonomy term.
 *
 * @since 0.27.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const OnlineEvent = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	// Get the online event link from meta.
	const onlineEventLinkMetaData = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				.gatherpress_online_event_link,
	);

	// Derive the venue taxonomy from the current editor post type.
	const { venueTaxonomy, editorPostType } = useSelect( ( select ) => {
		const currentEditorPostType = select( 'core/editor' )?.getCurrentPostType();
		return {
			editorPostType: currentEditorPostType,
			venueTaxonomy: getVenueTaxonomy( getVenuePostType( currentEditorPostType ) ),
		};
	}, [] );

	// Read the singular label so the panel title reflects what the post type
	// is actually called — a renamed event post type with
	// `singular_name => 'Happening'` shows "This is an online Happening" without any
	// extra wiring (#1612).
	const singularLabel = usePostTypeLabel(
		'singular_name',
		editorPostType,
		__( 'Event', 'gatherpress' )
	);

	// Get current venue taxonomy terms.
	const venueTermIds = useSelect( ( select ) =>
		select( 'core/editor' ).getEditedPostAttribute( venueTaxonomy ),
	);

	// Get the online-event term to find its ID.
	const onlineEventTerm = useSelect( ( select ) => {
		const terms = select( 'core' ).getEntityRecords( 'taxonomy', venueTaxonomy, {
			slug: 'online-event',
			per_page: 1,
		} );
		return terms?.[ 0 ] || null;
	}, [ venueTaxonomy ] );

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
			newTerms = hasTermAlready
				? currentTerms
				: [ ...currentTerms, termId ];
		} else {
			// Remove the online-event term.
			newTerms = currentTerms.filter(
				( id ) => String( id ) !== termIdStr
			);
		}

		editPost( { [ venueTaxonomy ]: newTerms } );
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

	return (
		<VStack spacing={ 3 }>
			<ToggleControl
				label={ sprintf(
					/* translators: %s: Singular post type label, e.g. "Event". */
					__( 'This is an online %s', 'gatherpress' ),
					singularLabel
				) }
				checked={ isOnlineEvent }
				onChange={ handleToggleChange }
			/>
			{ isOnlineEvent && (
				<TextControl
					type="url"
					label={ sprintf(
						/* translators: %s: Singular post type label, e.g. "Event". */
						__( 'Online %s link', 'gatherpress' ),
						singularLabel
					) }
					value={ onlineEventLink }
					placeholder={ sprintf(
						/* translators: %s: Singular post type label, e.g. "Event". */
						__( 'Add link to online %s', 'gatherpress' ),
						singularLabel
					) }
					onChange={ ( value ) => {
						updateEventLink( value );
					} }
				/>
			) }
		</VStack>
	);
};

export default OnlineEvent;
