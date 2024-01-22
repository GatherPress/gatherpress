/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import EditCover from '../../components/EditCover';

/**
 * Edit component for the GatherPress Add to Calendar block.
 *
 * This component renders the edit view of the GatherPress Add to Calendar block.
 * It provides an interface for users to add the event to their calendar.
 * The component includes an icon and a link for adding the event to the calendar.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<EditCover>
				<Flex justify="normal" align="center" gap="4">
					<FlexItem display="flex" className="gp-event-date__icon">
						<Icon icon="calendar" />
					</FlexItem>
					<FlexItem>
						{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
						<a href="#">{__('Add to calendar', 'gatherpress')}</a>
					</FlexItem>
				</Flex>
			</EditCover>
		</div>
	);
};

export default Edit;
