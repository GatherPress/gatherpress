/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon } from '@wordpress/components';

const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<Flex justify="normal" align="flex-start" gap="4">
				<FlexItem
					display="flex"
					className="wp-block-gatherpress-event-date__icon"
				>
					<Icon icon="calendar" />
				</FlexItem>
				<FlexItem>
					{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
					<a href="#">{__('Add to calendar', 'gatherpress')}</a>
				</FlexItem>
			</Flex>
		</div>
	);
};

export default Edit;
