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

const Edit = ({ isSelected }) => {
	const blockProps = useBlockProps();
	const onlineEventLink = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<PanelRow>
						<OnlineEventLink />
					</PanelRow>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<EditCover isSelected={isSelected}>
					<OnlineEvent onlineEventLinkDefault={onlineEventLink} />
				</EditCover>
			</div>
		</>
	);
};

export default Edit;
