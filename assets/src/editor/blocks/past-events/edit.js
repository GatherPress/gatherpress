import PastEvents from './components/PastEvents';
const { RichText, InspectorControls } = wp.blockEditor;
const { ToggleControl, PanelBody, PanelRow, CheckboxControl, SelectControl, ColorPicker } = wp.components;

const Edit = (props) => {
	const { attributes, setAttributes } = props;

	return(
		<div>
			<InspectorControls>
				<PanelBody>
					<PanelRow>
						<SelectControl
						label="Maximum number to display?"
						value={attributes.maxNumberOfEvents}
						options={[
							{label: "5", value: '5'},
							{label: "4", value: '4'},
							{label: "3", value: '3'},
							{label: "2", value: '2'},
							{label: "1", value: '1'},
						]}
						onChange={(newval) => setAttributes({ maxNumberOfEvents: newval })}
						/>
					</PanelRow>
				</PanelBody>
			</InspectorControls>
			<PastEvents maxNumberOfEvents={attributes.maxNumberOfEvents} />
		</div>
	);
}

export default Edit;
