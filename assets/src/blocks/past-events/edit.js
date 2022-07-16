/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import EventsList from '../components/EventsList';

const Edit = ( props ) => {
	const { attributes, setAttributes } = props;

	return (
		<div>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						label={__( 'Maximum number to display?', 'gatherpress' )}
						value={attributes.maxNumberOfEvents}
						// @todo possibly make this max list a setting.
						options={[
							{label: '5', value: '5'},
							{label: '4', value: '4'},
							{label: '3', value: '3'},
							{label: '2', value: '2'},
							{label: '1', value: '1'}
						]}
						onChange={( newVal ) => setAttributes({ maxNumberOfEvents: newVal })}
					/>
				</PanelBody>
			</InspectorControls>
			<EventsList maxNumberOfEvents={attributes.maxNumberOfEvents} type="past" />
		</div>
	);
};

export default Edit;
