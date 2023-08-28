/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem } from '@wordpress/components';
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
		<div {...blockProps}>
			<EditCover isSelected={isSelected}>
				{isSelected && (
					<Flex justify="normal">
						<FlexItem>
							<OnlineEventLink />
						</FlexItem>
					</Flex>
				)}
				{!isSelected && (
					<OnlineEvent onlineEventLinkDefault={onlineEventLink} />
				)}
			</EditCover>
		</div>
	);
};

export default Edit;
