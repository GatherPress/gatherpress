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

const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	const { icon, iconColor, iconSize } = attributes;
	const [svgContent, setSvgContent] = useState('');
	const svgBaseUrl = `${getFromGlobal('urls.pluginUrl')}assets/svg/`;

	// Icon original source: https://github.com/WordPress/dashicons/tree/master/svg-min.
	const ICON_OPTIONS = [
		{ label: __('Calendar', 'gatherpress'), value: 'calendar' },
		{ label: __('Yes Alt', 'gatherpress'), value: 'yes-alt' },
	];

	useEffect(() => {
		if (icon) {
			fetch(`${svgBaseUrl}${icon}.svg`)
				.then((res) => res.text())
				.then((svg) => setSvgContent(svg))
				.catch(() =>
					setSvgContent(
						'<svg><text x="0" y="15">SVG Error</text></svg>'
					)
				);
		}
	}, [icon, svgBaseUrl]);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Icon Settings', 'gatherpress')}>
					<SelectControl
						label={__('Icon', 'gatherpress')}
						value={icon}
						options={ICON_OPTIONS}
						onChange={(selectedIcon) =>
							setAttributes({ icon: selectedIcon })
						}
					/>
					<ColorPalette
						label={__('Color', 'gatherpress')}
						value={iconColor}
						clearable={true}
						onChange={(newColor) =>
							setAttributes({ iconColor: newColor })
						}
					/>
					<RangeControl
						label={__('Size', 'gatherpress')}
						value={iconSize}
						onChange={(newSize) =>
							setAttributes({ iconSize: newSize })
						}
						min={8}
						max={64}
						initialPosition={20}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<div
					style={{
						fill: iconColor || 'inherit',
						width: `${iconSize}px`,
						height: `${iconSize}px`,
						lineHeight: 0,
					}}
					dangerouslySetInnerHTML={{ __html: svgContent }}
				/>
			</div>
		</>
	);
};

export default Edit;
