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

const Edit = ({ isSelected }) => {
	const blockProps = useBlockProps();
	const onlineEventLink = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);

	return (
		<div {...blockProps}>
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
		</div>
	);
};

export default Edit;
