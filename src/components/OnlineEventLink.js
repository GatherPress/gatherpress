/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster, Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

/**
 * OnlineEventLink component for GatherPress.
 *
 * This component provides a TextControl input for adding or editing the online event link
 * associated with a post in the WordPress editor. It updates the post meta and broadcasts
 * the change to other components.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const OnlineEventLink = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const onlineEventLinkMetaData = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				.gatherpress_online_event_link,
	);
	const [ onlineEventLink, setOnlineEventLink ] = useState(
		onlineEventLinkMetaData,
	);
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

	Listener( { setOnlineEventLink }, getFromGlobal( 'eventDetails.postId' ) );

	return (
		<TextControl
			label={ __( 'Online event link', 'gatherpress' ) }
			value={ onlineEventLink }
			placeholder={ __( 'Add link to online event', 'gatherpress' ) }
			onChange={ ( value ) => {
				updateEventLink( value );
			} }
		/>
	);
};

export default OnlineEventLink;
