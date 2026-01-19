/**
 * WordPress dependencies.
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ColorPalette,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getFromGlobal } from '../../helpers/globals';
import { useEffect, useState } from '@wordpress/element';

const Edit = ( { attributes, setAttributes } ) => {
	const blockProps = useBlockProps();
	const { icon, iconColor, iconSize } = attributes;
	const [ svgContent, setSvgContent ] = useState( '' );
	const svgBaseUrl = `${ getFromGlobal( 'urls.pluginUrl' ) }includes/assets/svg/`;

	// Icon original source: https://github.com/WordPress/dashicons/tree/master/svg-min.
	const ICON_OPTIONS = [
		{ label: __( 'Admin Site Alt3', 'gatherpress' ), value: 'admin-site-alt3' },
		{ label: __( 'Calendar', 'gatherpress' ), value: 'calendar' },
		{ label: __( 'Clock', 'gatherpress' ), value: 'clock' },
		{ label: __( 'Dismiss', 'gatherpress' ), value: 'dismiss' },
		{ label: __( 'Editor Help', 'gatherpress' ), value: 'editor-help' },
		{ label: __( 'Groups', 'gatherpress' ), value: 'groups' },
		{ label: __( 'Location', 'gatherpress' ), value: 'location' },
		{ label: __( 'Nametag', 'gatherpress' ), value: 'nametag' },
		{ label: __( 'Phone', 'gatherpress' ), value: 'phone' },
		{ label: __( 'Yes Alt', 'gatherpress' ), value: 'yes-alt' },
	];

	useEffect( () => {
		if ( icon ) {
			fetch( `${ svgBaseUrl }${ icon }.svg` )
				.then( ( res ) => res.text() )
				.then( ( svg ) => setSvgContent( svg ) )
				.catch( () =>
					setSvgContent(
						`<svg><text x="0" y="15">${ __( 'SVG Error', 'gatherpress' ) }</text></svg>`,
					),
				);
		}
	}, [ icon, svgBaseUrl ] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon Settings', 'gatherpress' ) }>
					<SelectControl
						label={ __( 'Icon', 'gatherpress' ) }
						value={ icon }
						options={ ICON_OPTIONS }
						onChange={ ( selectedIcon ) =>
							setAttributes( { icon: selectedIcon } )
						}
					/>
					<ColorPalette
						label={ __( 'Color', 'gatherpress' ) }
						value={ iconColor }
						clearable={ true }
						onChange={ ( newColor ) =>
							setAttributes( { iconColor: newColor } )
						}
					/>
					<RangeControl
						label={ __( 'Size', 'gatherpress' ) }
						value={ iconSize }
						onChange={ ( newSize ) =>
							setAttributes( { iconSize: newSize } )
						}
						min={ 8 }
						max={ 240 }
						initialPosition={ 24 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div
					style={ {
						fill: iconColor || 'inherit',
						width: `${ iconSize }px`,
						height: `${ iconSize }px`,
						lineHeight: 0,
					} }
					dangerouslySetInnerHTML={ { __html: svgContent } }
				/>
			</div>
		</>
	);
};

export default Edit;
