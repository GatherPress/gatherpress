/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Rsvp from '../../components/Rsvp';
import { getFromGlobal, isSinglePostInEditor } from '../../helpers/globals';
import EditCover from '../../components/EditCover';
import InitialDecline from '../../components/InitialDecline';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component renders the edit view of the GatherPress RSVP block.
 * It provides an interface for users to respond to the RSVP for the associated event.
 * The component includes the RSVP component and passes the event ID, current user,
 * and type of RSVP as props.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = () => {
	const blockProps = useBlockProps();
	const postId = getFromGlobal('eventDetails.postId');
	const currentUser = getFromGlobal('eventDetails.currentUser');

	return (
		<div {...blockProps}>
			<EditCover>
				<Rsvp
					postId={postId}
					currentUser={currentUser}
					type={'upcoming'}
				/>
				{isSinglePostInEditor() && (
					<InspectorControls>
						<PanelBody>
							<h3>{__('RSVP Options', 'gatherpress')}</h3>
							<InitialDecline/>
						</PanelBody>
					</InspectorControls>
				)}
			</EditCover>
		</div>
	);
};

export default Edit;
