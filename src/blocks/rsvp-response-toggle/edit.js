import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

const Edit = ( { attributes, setAttributes, context } ) => {
	const blockProps = useBlockProps();
	const { showAll, showFewer } = attributes;
	const [ isShowingAll, setIsShowingAll ] = useState( true );
	const isLimitEnabled = context?.[ 'gatherpress/rsvpLimitEnabled' ] ?? false;

	if ( ! isLimitEnabled ) {
		return null;
	}

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody>
					<ToggleControl
						label={ __( 'Edit display text', 'gatherpress' ) }
						help={
							isShowingAll
								? __(
									'Set text for showing all RSVP responses',
									'gatherpress',
								)
								: __(
									'Set text for showing fewer RSVP responses',
									'gatherpress',
								)
						}
						checked={ isShowingAll }
						onChange={ () => setIsShowingAll( ! isShowingAll ) }
					/>
				</PanelBody>
			</InspectorControls>
			<RichText
				tagName="a"
				value={ isShowingAll ? showAll : showFewer }
				onChange={ ( newValue ) =>
					setAttributes(
						isShowingAll
							? { showAll: newValue }
							: { showFewer: newValue },
					)
				}
				allowedFormats={ [] }
				multiline={ false }
				style={ { whiteSpace: 'nowrap' } }
			/>
		</div>
	);
};

export default Edit;
