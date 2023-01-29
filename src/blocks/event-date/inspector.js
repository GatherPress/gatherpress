

const coverAdvancedControls = wp.compose.createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { Fragment } = wp.element;
		const { ToggleControl } = wp.components;
		const { InspectorAdvancedControls } = wp.blockEditor;
		const { attributes, setAttributes, isSelected } = props;
		return (
			<Fragment>
				<BlockEdit {...props} />
				{isSelected && (props.name == 'gatherpress/event-venue') && 
					<InspectorAdvancedControls>
						<ToggleControl
							label={wp.i18n.__('Hide on mobile', 'gatherpress')}
							checked={!!attributes.hideOnMobile}
							onChange={(newval) => setAttributes({ hideOnMobile: !attributes.hideOnMobile })}
						/>
					</InspectorAdvancedControls>
				}
			</Fragment>
		);
	};
}, 'coverAdvancedControls');
 
wp.hooks.addFilter(
	'editor.BlockEdit',
	'gatherpress/cover-advanced-control',
	coverAdvancedControls
);
