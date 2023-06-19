/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Flex,
	FlexItem,
	Icon,
	TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

const Edit = ({ attributes, setAttributes, isSelected }) => {
	const blockProps = useBlockProps();
	const editPost = useDispatch('core/editor').editPost;
	const { onlineEventLink } = attributes;
	const onlineEventLinkMetaData = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);

	const onUpdate = (value) => {
		const meta = { _online_event_link: value };

		setAttributes({ onlineEventLink: value });
		editPost({ meta });
	};

	useEffect(() => {
		setAttributes({
			onlineEventUrl: onlineEventLinkMetaData ?? '',
		});
	}, [setAttributes, onlineEventLinkMetaData]);

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
								onUpdate(value);
							}}
						/>
					</FlexItem>
				</Flex>
			)}
			{!isSelected && (
				<Flex justify="normal">
					<FlexItem display="flex">
						<Icon icon="video-alt2" />
					</FlexItem>
					<FlexItem>
						<a href={onlineEventLink}>
							{__('Online event', 'gatherpress')}
						</a>
					</FlexItem>
				</Flex>
			)}
		</div>
	);
};

export default Edit;
