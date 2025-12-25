/**
 * External dependencies.
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * WordPress dependencies.
 */
import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
	InspectorAdvancedControls,
	PanelColorSettings,
	RichText,
} from '@wordpress/block-editor';
import {
	BoxControl,
	PanelBody,
	ToolbarButton,
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	ToolbarGroup,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { dispatch, select, useSelect } from '@wordpress/data';

/**
 * Edit component for the GatherPress Dropdown block.
 *
 * This component is used in the WordPress editor to manage the editable interface
 * for the GatherPress Dropdown block. It allows users to configure the block's
 * attributes and settings directly within the editor.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               The props object passed to the component.
 * @param {Object}   props.attributes    The attributes for the block.
 * @param {Function} props.setAttributes A function to update block attributes.
 * @param {string}   props.clientId      The unique identifier for the block instance.
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ( { attributes, setAttributes, clientId } ) => {
	const blockProps = useBlockProps();
	const [ isExpanded, setIsExpanded ] = useState( false );
	const {
		actAsSelect,
		dropdownBorderColor,
		dropdownBorderRadius,
		dropdownBorderThickness,
		dropdownId,
		dropdownWidth,
		dropdownZIndex,
		itemBgColor,
		itemDividerColor,
		itemDividerThickness,
		itemHoverBgColor,
		itemHoverTextColor,
		itemPadding,
		itemTextColor,
		label,
		labelTextColor,
		openOn,
		selectedIndex,
	} = attributes;
	const innerBlocks = useSelect(
		( blockEditorSelect ) =>
			blockEditorSelect( 'core/block-editor' ).getBlock( clientId )
				?.innerBlocks || [],
		[ clientId ],
	);

	// Generate a persistent unique ID for the dropdown if not already set.
	useEffect( () => {
		if ( ! dropdownId ) {
			const newDropdownId = `dropdown-${ uuidv4() }`;
			setAttributes( { dropdownId: newDropdownId } );
		}
	}, [ dropdownId, setAttributes ] );

	// Update `metadata.name` with the label value for the List View.
	useEffect( () => {
		const currentLabel = label || __( 'Dropdown', 'gatherpress' );
		const currentMetadata =
			select( 'core/block-editor' ).getBlockAttributes( clientId ).metadata ||
			{};

		// Only update if the metadata name differs from the current label.
		if ( currentMetadata.name !== currentLabel ) {
			dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, {
				metadata: { ...currentMetadata, name: currentLabel },
			} );
		}
	}, [ label, clientId ] );

	useEffect( () => {
		// Ensure this effect only runs when `actAsSelect` is enabled.
		if ( ! actAsSelect ) {
			return;
		}

		// Validate innerBlocks exists and has items
		if ( ! Array.isArray( innerBlocks ) || ! innerBlocks.length ) {
			return;
		}

		// Validate selectedIndex is within bounds
		if ( 0 > selectedIndex || selectedIndex >= innerBlocks.length ) {
			return;
		}

		const selectedBlock = innerBlocks[ selectedIndex ];
		const selectedBlockText = selectedBlock?.attributes?.text || '';

		// Parse and extract plain text to remove any markup.
		const plainTextLabel = new DOMParser()
			.parseFromString( selectedBlockText, 'text/html' )
			.body.textContent.trim();

		// Update the label if it differs from the current one.
		if ( plainTextLabel && plainTextLabel !== label ) {
			setAttributes( { label: plainTextLabel } );
		}
	}, [ actAsSelect, selectedIndex, innerBlocks, label, setAttributes ] );

	useEffect( () => {
		// Only run if actAsSelect is enabled and there are inner blocks
		if ( ! actAsSelect || ! innerBlocks.length ) {
			return;
		}

		// Check if selectedIndex is valid
		if ( 0 > selectedIndex || selectedIndex >= innerBlocks.length ) {
			return;
		}

		const selectedBlockText =
			innerBlocks[ selectedIndex ]?.attributes?.text || '';

		// Parse the selected block's text to remove any HTML markup.
		const plainTextLabel = new DOMParser()
			.parseFromString( selectedBlockText, 'text/html' )
			.body.textContent.trim();

		const newLabel =
			plainTextLabel ||
			__( 'Item', 'gatherpress' ) + ` ${ selectedIndex + 1 }`;

		// Only update if the label has changed
		if ( newLabel !== label ) {
			setAttributes( { label: newLabel } );
		}
	}, [ innerBlocks, actAsSelect, selectedIndex, label, setAttributes ] );

	const dropdownStyles = `
		#${ dropdownId } .wp-block-gatherpress-dropdown-item {
			padding: ${ parseInt( itemPadding?.top || 0, 10 ) }px
				${ parseInt( itemPadding?.right || 0, 10 ) }px
				${ parseInt( itemPadding?.bottom || 0, 10 ) }px
				${ parseInt( itemPadding?.left || 0, 10 ) }px;
			color: ${ itemTextColor || 'inherit' };
			background-color: ${ itemBgColor || 'transparent' };
		}

		#${ dropdownId } .wp-block-gatherpress-dropdown-item:hover {
			color: ${ itemHoverTextColor || 'inherit' };
			background-color: ${ itemHoverBgColor || 'transparent' };
		}

		#${ dropdownId } .wp-block-gatherpress-dropdown-item:not(:first-child) {
			border-top: ${ itemDividerThickness || 1 }px solid ${ itemDividerColor || 'transparent' };
		}
	`;

	// Toggle dropdown visibility.
	const handleToggle = () => {
		setIsExpanded( ( prev ) => ! prev );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Dropdown Settings', 'gatherpress' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Dropdown Z-Index', 'gatherpress' ) }
						value={ dropdownZIndex }
						onChange={ ( value ) =>
							setAttributes( { dropdownZIndex: value } )
						}
						min={ 0 }
						max={ 9999 }
					/>
					<RangeControl
						label={ __( 'Dropdown Width', 'gatherpress' ) }
						value={ parseInt( dropdownWidth, 10 ) }
						onChange={ ( value ) =>
							setAttributes( { dropdownWidth: value } )
						}
						min={ 100 }
						max={ 300 }
					/>
					<RangeControl
						label={ __( 'Dropdown Border Thickness', 'gatherpress' ) }
						value={ dropdownBorderThickness || 1 }
						onChange={ ( value ) =>
							setAttributes( { dropdownBorderThickness: value } )
						}
						min={ 0 }
						max={ 20 }
					/>
					<RangeControl
						label={ __( 'Dropdown Border Radius', 'gatherpress' ) }
						value={ dropdownBorderRadius }
						onChange={ ( value ) =>
							setAttributes( { dropdownBorderRadius: value } )
						}
						min={ 0 }
						max={ 50 }
					/>
					<BoxControl
						label={ __( 'Item Padding', 'gatherpress' ) }
						values={ itemPadding || 8 }
						onChange={ ( value ) =>
							setAttributes( { itemPadding: value } )
						}
					/>
					<RangeControl
						label={ __( 'Item Divider Thickness', 'gatherpress' ) }
						value={ itemDividerThickness || 1 }
						onChange={ ( value ) =>
							setAttributes( { itemDividerThickness: value } )
						}
						min={ 0 }
						max={ 10 }
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Label Colors', 'gatherpress' ) }
					colorSettings={ [
						{
							value: labelTextColor,
							onChange: ( value ) =>
								setAttributes( { labelTextColor: value } ),
							label: __( 'Label Text Color', 'gatherpress' ),
						},
					] }
				/>
				<PanelColorSettings
					title={ __( 'Dropdown Colors', 'gatherpress' ) }
					colorSettings={ [
						{
							value: dropdownBorderColor,
							onChange: ( value ) =>
								setAttributes( { dropdownBorderColor: value } ),
							label: __( 'Dropdown Border Color', 'gatherpress' ),
						},
					] }
				/>
				<PanelColorSettings
					title={ __( 'Item Colors', 'gatherpress' ) }
					colorSettings={ [
						{
							value: itemTextColor,
							onChange: ( value ) =>
								setAttributes( { itemTextColor: value } ),
							label: __( 'Item Text Color', 'gatherpress' ),
						},
						{
							value: itemBgColor,
							onChange: ( value ) =>
								setAttributes( { itemBgColor: value } ),
							label: __( 'Item Background Color', 'gatherpress' ),
						},
						{
							value: itemHoverTextColor,
							onChange: ( value ) =>
								setAttributes( { itemHoverTextColor: value } ),
							label: __( 'Item Hover Text Color', 'gatherpress' ),
						},
						{
							value: itemHoverBgColor,
							onChange: ( value ) =>
								setAttributes( { itemHoverBgColor: value } ),
							label: __(
								'Item Hover Background Color',
								'gatherpress',
							),
						},
						{
							value: itemDividerColor,
							onChange: ( newColor ) =>
								setAttributes( { itemDividerColor: newColor } ),
							label: __( 'Item Divider Color', 'gatherpress' ),
						},
					] }
				/>
			</InspectorControls>
			<InspectorAdvancedControls>
				<ToggleGroupControl
					label={ __( 'Open on', 'gatherpress' ) }
					value={ openOn }
					isBlock
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					onChange={ ( value ) => setAttributes( { openOn: value } ) }
				>
					<ToggleGroupControlOption
						value="click"
						label={ __( 'Click', 'gatherpress' ) }
					/>
					<ToggleGroupControlOption
						value="hover"
						label={ __( 'Hover', 'gatherpress' ) }
					/>
				</ToggleGroupControl>
				{ 0 < innerBlocks.length && (
					<>
						<ToggleControl
							label={ __( 'Enable Select Mode', 'gatherpress' ) }
							help={ __(
								'When enabled, clicking on an item will set it as the dropdown label, and the selected item will be disabled until another is chosen.',
								'gatherpress',
							) }
							checked={ actAsSelect }
							onChange={ ( value ) =>
								setAttributes( { actAsSelect: value } )
							}
						/>
						{ actAsSelect && (
							<SelectControl
								label={ __(
									'Default Selected Item',
									'gatherpress',
								) }
								help={ __(
									'This item will be selected by default when the dropdown is displayed.',
									'gatherpress',
								) }
								value={ selectedIndex }
								options={ innerBlocks.map( ( block, index ) => {
									// Parse and extract plain text to remove markup.
									const plainTextLabel = new DOMParser()
										.parseFromString(
											block.attributes.text || '',
											'text/html',
										)
										.body.textContent.trim();

									/* translators: %d is the index of the item. */
									const labelTemplate = __(
										'Item %d',
										'gatherpress',
									);

									return {
										label:
											plainTextLabel ||
											sprintf( labelTemplate, index + 1 ),
										value: index,
									};
								} ) }
								onChange={ ( value ) =>
									setAttributes( {
										selectedIndex: parseInt( value, 10 ),
									} )
								}
							/>
						) }
					</>
				) }
			</InspectorAdvancedControls>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={ isExpanded ? 'no-alt' : 'plus' }
						onClick={ handleToggle }
						label={
							isExpanded
								? __( 'Close Dropdown', 'gatherpress' )
								: __( 'Open Dropdown', 'gatherpress' )
						}
					/>
				</ToolbarGroup>
			</BlockControls>

			{ actAsSelect ? (
				// Use plain anchor when actAsSelect is enabled.
				// eslint-disable-next-line jsx-a11y/anchor-is-valid
				<a
					href="#"
					role="button"
					aria-expanded={ isExpanded }
					aria-controls={ dropdownId }
					tabIndex={ 0 }
					className="wp-block-gatherpress-dropdown__trigger"
					style={ {
						color: labelTextColor,
					} }
				>
					{ label }
				</a>
			) : (
				// Use RichText when actAsSelect is disabled.
				<RichText
					tagName="a"
					href="#"
					role="button"
					aria-expanded={ isExpanded }
					aria-controls={ dropdownId }
					tabIndex={ 0 }
					className="wp-block-gatherpress-dropdown__trigger"
					value={ label }
					onChange={ ( value ) => {
						setAttributes( { label: value } );
					} }
					allowedFormats={ [] }
					placeholder={ __( 'Dropdown Label…', 'gatherpress' ) }
					style={ {
						color: labelTextColor,
					} }
				/>
			) }

			<style>{ dropdownStyles }</style>
			<div
				id={ dropdownId }
				className="wp-block-gatherpress-dropdown__menu"
				style={ {
					display: isExpanded ? 'block' : 'none',
					backgroundColor: itemBgColor,
					border: `${ dropdownBorderThickness || 1 }px solid ${ dropdownBorderColor || '#000000' }`,
					borderRadius: `${ dropdownBorderRadius || 0 }px`,
					width: dropdownWidth,
				} }
			>
				<InnerBlocks allowedBlocks={ [ 'gatherpress/dropdown-item' ] } />
			</div>
		</div>
	);
};

export default Edit;
