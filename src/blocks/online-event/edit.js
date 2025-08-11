/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import OnlineEvent from '../../components/OnlineEvent';
import OnlineEventLink from '../../components/OnlineEventLink';
import EditCover from '../../components/EditCover';
import { isGatherPressPostType } from '../../helpers/editor';

/**
 * Edit component for the GatherPress Online Event block.
 *
 * This component renders the edit view of the GatherPress Online Event block.
 * It provides an interface for users to add an online event link.
 * The component includes an inspector control for managing the online event link.
 *
 * @since 1.0.0
 *
 * @param {Object}  props            - The component properties.
 * @param {boolean} props.isSelected - Indicates whether the block is selected.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { isSelected } ) => {
	const blockProps = useBlockProps();
	const onlineEventLink = useSelect(
		( select ) =>
			select( 'core/editor' )?.getEditedPostAttribute( 'meta' )
				?.gatherpress_online_event_link,
	);

	return (
		<>
			{ isGatherPressPostType() && (
				<InspectorControls>
					<PanelBody>
						<PanelRow>
							<OnlineEventLink />
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...blockProps }>
				<EditCover isSelected={ isSelected }>
					<OnlineEvent onlineEventLinkDefault={ onlineEventLink } />
				</EditCover>
			</div>
		</>
	);
};

export default Edit;
