/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { BlockControls, RichText } from '@wordpress/block-editor';
import {
	Popover,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useState, useRef } from '@wordpress/element';
import { link as linkIcon } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { cleanUrlForDisplay } from '../helpers';

/**
 * URL field component for venue details.
 *
 * Renders an editable URL field with a link settings popover
 * for configuring target and URL display options.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - Component props.
 * @param {string}   props.value         - The current field value.
 * @param {Function} props.onChange      - Callback when value changes.
 * @param {string}   props.placeholder   - Placeholder text.
 * @param {Function} props.onKeyDown     - Keyboard event handler.
 * @param {string}   props.linkTarget    - Link target attribute (_blank or _self).
 * @param {boolean}  props.cleanUrl      - Whether to display cleaned URL.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @param {boolean}  props.disabled      - Whether the field is disabled.
 * @return {JSX.Element} The rendered URL field.
 */
const UrlField = ( {
	value,
	onChange,
	placeholder,
	onKeyDown,
	linkTarget,
	cleanUrl,
	setAttributes,
	disabled,
} ) => {
	const [ isLinkPopoverOpen, setIsLinkPopoverOpen ] = useState( false );
	const [ isUrlFieldFocused, setIsUrlFieldFocused ] = useState( false );
	const linkButtonRef = useRef( null );

	// When focused, show raw URL for editing. When blurred, show cleaned URL if enabled.
	let displayValue = value;
	if ( ! isUrlFieldFocused && cleanUrl ) {
		displayValue = cleanUrlForDisplay( value );
	}

	const placeholderText =
		placeholder || __( 'Venue website URL…', 'gatherpress' );

	const baseClass = 'gatherpress-venue-detail__url';

	// Render non-editable placeholder when disabled.
	if ( disabled ) {
		return (
			<span
				className={ `${ baseClass } gatherpress-venue-detail--disabled` }
			>
				<span className="wp-block-gatherpress-venue-detail__placeholder">
					{ placeholderText }
				</span>
			</span>
		);
	}

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						ref={ linkButtonRef }
						icon={ linkIcon }
						title={ __( 'Link settings', 'gatherpress' ) }
						onClick={ () =>
							setIsLinkPopoverOpen( ! isLinkPopoverOpen )
						}
						isPressed={ isLinkPopoverOpen }
					/>
				</ToolbarGroup>
			</BlockControls>
			{ isLinkPopoverOpen && (
				<Popover
					anchor={ linkButtonRef.current }
					onClose={ () => setIsLinkPopoverOpen( false ) }
					placement="bottom"
					shift
				>
					<div
						style={ {
							padding: '16px',
							width: '280px',
						} }
					>
						<ToggleControl
							label={ __( 'Open in new tab', 'gatherpress' ) }
							checked={ '_blank' === linkTarget }
							onChange={ ( newValue ) =>
								setAttributes( {
									linkTarget: newValue ? '_blank' : '_self',
								} )
							}
						/>
						<ToggleControl
							label={ __( 'Clean URL display', 'gatherpress' ) }
							checked={ cleanUrl }
							onChange={ ( newValue ) =>
								setAttributes( { cleanUrl: newValue } )
							}
						/>
					</div>
				</Popover>
			) }
			<RichText
				tagName={ value ? 'a' : 'span' }
				href={ value || undefined }
				target={
					value && '_blank' === linkTarget ? '_blank' : undefined
				}
				rel={
					value && '_blank' === linkTarget
						? 'noopener noreferrer'
						: undefined
				}
				className={ baseClass }
				value={ displayValue }
				onChange={ onChange }
				placeholder={ placeholderText }
				allowedFormats={ [] }
				onKeyDown={ onKeyDown }
				onFocus={ () => setIsUrlFieldFocused( true ) }
				onBlur={ () => setIsUrlFieldFocused( false ) }
				onClick={ ( e ) => value && e.preventDefault() }
			/>
		</>
	);
};

export default UrlField;
