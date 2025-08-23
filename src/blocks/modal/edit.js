/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect, select, dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	Button,
	RangeControl,
	TextControl,
} from '@wordpress/components';

const Edit = ( { attributes, setAttributes, clientId, isSelected } ) => {
	const hasSelectedInnerBlock = useSelect(
		( blockEditorSelect ) =>
			blockEditorSelect( blockEditorStore ).hasSelectedInnerBlock(
				clientId,
				true,
			),
		[ clientId ],
	);

	const blockProps = useBlockProps( {
		style: {
			display: isSelected || hasSelectedInnerBlock ? 'block' : 'none',
			maxWidth: 'none',
		},
	} );
	const { zIndex, metadata = {} } = attributes;

	const modalManagerClientId = select( 'core/block-editor' ).getBlockParents(
		clientId,
		{ levels: 1 },
	)?.[ 0 ];

	const goToModalManager = () => {
		if ( modalManagerClientId ) {
			dispatch( 'core/block-editor' ).selectBlock( modalManagerClientId );
		}
	};

	const handleNameChange = ( value ) => {
		setAttributes( {
			metadata: {
				...metadata,
				name: value,
			},
		} );
	};

	const TEMPLATE = [ [ 'gatherpress/modal-content', {} ] ];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Modal Settings', 'gatherpress' ) }>
					<TextControl
						label={ __( 'Modal Name', 'gatherpress' ) }
						value={ metadata.name || __( 'Modal', 'gatherpress' ) }
						onChange={ handleNameChange }
						help={ __(
							'Set a unique name for this modal. This will be used as the aria-label.',
							'gatherpress',
						) }
					/>
					<RangeControl
						label={ __( 'Z-Index', 'gatherpress' ) }
						value={ zIndex }
						onChange={ ( newValue ) =>
							setAttributes( { zIndex: newValue } )
						}
						min={ 0 }
						max={ 9999 }
						step={ 1 }
						help={ __(
							'Set the layering position of the modal.',
							'gatherpress',
						) }
					/>
					<Button variant="secondary" onClick={ goToModalManager }>
						{ __( 'Back to Modal Manager', 'gatherpress' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks template={ TEMPLATE } />
			</div>
		</>
	);
};

export default Edit;
