/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, TextControl } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import OnlineEvent from '../../components/OnlineEvent';
import { getFromGlobal } from '../../helpers/globals';

const Edit = ({ attributes, setAttributes, isSelected }) => {
	const blockProps = useBlockProps();
	const { onlineEventLink } = attributes;

	return (
		<div {...blockProps}>
			{isSelected && (
				<Flex justify="normal">
					<FlexItem>
						<TextControl
							label={__('Online event link', 'gatherpress')}
							value={onlineEventLink}
							placeholder={__(
								'Add link to online event',
								'gatherpress'
							)}
							onChange={(value) => {
								setAttributes({ onlineEventLink: value });
							}}
						/>
					</FlexItem>
				</Flex>
			)}
			{!isSelected && (
				<OnlineEvent
					eventId={getFromGlobal('post_id')}
					onlineEventLinkDefault={onlineEventLink}
				/>
			)}
		</div>
	);
};

export default Edit;
