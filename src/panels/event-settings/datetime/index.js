/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button, Dropdown, Flex, FlexItem, PanelRow, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../../../helpers/broadcasting';
import { DateTimeStartLabel, DateTimeEndLabel } from '../../../components/DateTime';

const DateTimePanel = ( props ) => {
	return (
		<section>
			<h3>{ __( 'Date & time', 'gatherpress' ) }</h3>
			<PanelRow>
				<Flex>
					<FlexItem>{ __( 'Start', 'gatherpress' ) }</FlexItem>
					<FlexItem>
						<Dropdown
							position="bottom left"
							renderToggle={ ( { isOpen, onToggle } ) => (
								<Button
									onClick={ onToggle }
									aria-expanded={ isOpen }
									isLink
								>
									<DateTimeStartLabel />
								</Button>
							) }
							renderContent={ () => '' }
						/>
					</FlexItem>
				</Flex>
			</PanelRow>
			<PanelRow>
				<Flex>
					<FlexItem>{ __( 'End', 'gatherpress' ) }</FlexItem>
					<FlexItem>here</FlexItem>
				</Flex>
			</PanelRow>
		</section>
	);
};

export default DateTimePanel;
