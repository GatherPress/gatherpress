/**
 * WordPress dependencies.
 */
import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	RichTextToolbarButton,
	useCachedTruthy,
} from '@wordpress/block-editor';
import {
	Popover,
	TextControl,
	ColorPicker,
	Button,
	Flex,
	FlexItem,
} from '@wordpress/components';
import {
	applyFormat,
	removeFormat,
	getActiveFormat,
	useAnchor,
} from '@wordpress/rich-text';
import { comment } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { FORMAT_NAME, DEFAULT_COLORS } from './constants';

/**
 * Get tooltip attributes from the active format.
 *
 * @param {Object} activeFormat The active format object.
 * @return {Object} Object containing tooltip, textColor, and bgColor.
 */
function getTooltipAttributes( activeFormat ) {
	if ( ! activeFormat?.attributes ) {
		return {
			tooltip: '',
			textColor: DEFAULT_COLORS.textColor,
			bgColor: DEFAULT_COLORS.bgColor,
		};
	}

	return {
		tooltip: activeFormat.attributes[ 'data-gatherpress-tooltip' ] || '',
		textColor:
			activeFormat.attributes[ 'data-gatherpress-tooltip-text-color' ] ||
			DEFAULT_COLORS.textColor,
		bgColor:
			activeFormat.attributes[ 'data-gatherpress-tooltip-bg-color' ] ||
			DEFAULT_COLORS.bgColor,
	};
}

/**
 * Tooltip popover component for editing tooltip content and colors.
 *
 * @since 1.0.0
 *
 * @param {Object}   props              Component props.
 * @param {Function} props.onClose      Callback when popover closes.
 * @param {Object}   props.value        RichText value object.
 * @param {Function} props.onChange     Callback when value changes.
 * @param {Object}   props.activeFormat The currently active format.
 * @param {Object}   props.contentRef   Reference to the content element.
 *
 * @return {JSX.Element} The popover component.
 */
function TooltipPopover( {
	onClose,
	value,
	onChange,
	activeFormat,
	contentRef,
} ) {
	const { tooltip, textColor, bgColor } = getTooltipAttributes( activeFormat );
	const [ tooltipText, setTooltipText ] = useState( tooltip );
	const [ tooltipTextColor, setTooltipTextColor ] = useState( textColor );
	const [ tooltipBgColor, setTooltipBgColor ] = useState( bgColor );
	const [ showTextColorPicker, setShowTextColorPicker ] = useState( false );
	const [ showBgColorPicker, setShowBgColorPicker ] = useState( false );

	const popoverAnchor = useAnchor( {
		editableContentElement: contentRef.current,
		settings: {
			name: FORMAT_NAME,
			title: __( 'Tooltip', 'gatherpress' ),
			tagName: 'span',
			className: 'gatherpress-tooltip',
		},
	} );

	const applyTooltip = useCallback( () => {
		if ( ! tooltipText.trim() ) {
			// Remove format if tooltip text is empty.
			onChange( removeFormat( value, FORMAT_NAME ) );
		} else {
			// Build attributes object, only including colors if they differ from defaults.
			// This allows theme CSS custom properties to control the default appearance.
			const attributes = {
				'data-gatherpress-tooltip': tooltipText,
			};

			// Only store color if it differs from the default.
			if ( tooltipTextColor !== DEFAULT_COLORS.textColor ) {
				attributes[ 'data-gatherpress-tooltip-text-color' ] =
					tooltipTextColor;
			}

			if ( tooltipBgColor !== DEFAULT_COLORS.bgColor ) {
				attributes[ 'data-gatherpress-tooltip-bg-color' ] =
					tooltipBgColor;
			}

			onChange(
				applyFormat( value, {
					type: FORMAT_NAME,
					attributes,
				} )
			);
		}
		onClose();
	}, [
		tooltipText,
		tooltipTextColor,
		tooltipBgColor,
		value,
		onChange,
		onClose,
	] );

	const removeTooltip = useCallback( () => {
		onChange( removeFormat( value, FORMAT_NAME ) );
		onClose();
	}, [ value, onChange, onClose ] );

	return (
		<Popover
			anchor={ popoverAnchor }
			onClose={ onClose }
			focusOnMount="firstElement"
			className="gatherpress-tooltip-popover"
		>
			<Flex
				direction="column"
				gap={ 4 }
				className="gatherpress-tooltip-popover__content"
			>
				<FlexItem>
					<TextControl
						label={ __( 'Tooltip Text', 'gatherpress' ) }
						value={ tooltipText }
						onChange={ setTooltipText }
						placeholder={ __( 'Enter tooltip text…', 'gatherpress' ) }
						__nextHasNoMarginBottom
					/>
				</FlexItem>

				<FlexItem>
					<Flex direction="column" gap={ 2 }>
						<FlexItem>
							<Flex justify="flex-start" gap={ 2 }>
								<FlexItem>
									<Button
										variant="secondary"
										onClick={ () => {
											setShowTextColorPicker(
												! showTextColorPicker
											);
											setShowBgColorPicker( false );
										} }
										style={ {
											backgroundColor: tooltipTextColor,
											color: tooltipBgColor,
											minWidth: '120px',
										} }
									>
										{ __( 'Text Color', 'gatherpress' ) }
									</Button>
								</FlexItem>
								<FlexItem>
									<Button
										variant="secondary"
										onClick={ () => {
											setShowBgColorPicker(
												! showBgColorPicker
											);
											setShowTextColorPicker( false );
										} }
										style={ {
											backgroundColor: tooltipBgColor,
											color: tooltipTextColor,
											minWidth: '120px',
										} }
									>
										{ __( 'Background', 'gatherpress' ) }
									</Button>
								</FlexItem>
							</Flex>
						</FlexItem>

						{ showTextColorPicker && (
							<FlexItem>
								<ColorPicker
									color={ tooltipTextColor }
									onChange={ setTooltipTextColor }
									enableAlpha={ false }
								/>
							</FlexItem>
						) }

						{ showBgColorPicker && (
							<FlexItem>
								<ColorPicker
									color={ tooltipBgColor }
									onChange={ setTooltipBgColor }
									enableAlpha={ false }
								/>
							</FlexItem>
						) }
					</Flex>
				</FlexItem>

				{ /* Preview section. */ }
				<FlexItem>
					<div className="gatherpress-tooltip-popover__preview">
						<span className="gatherpress-tooltip-popover__preview-label">
							{ __( 'Preview:', 'gatherpress' ) }
						</span>
						<span
							className="gatherpress-tooltip gatherpress-tooltip--preview"
							style={ {
								'--gatherpress-tooltip-text-color': tooltipTextColor,
								'--gatherpress-tooltip-bg-color': tooltipBgColor,
							} }
							data-gatherpress-tooltip={
								tooltipText ||
								__( 'Sample tooltip', 'gatherpress' )
							}
						>
							{ __( 'Hover me', 'gatherpress' ) }
						</span>
					</div>
				</FlexItem>

				<FlexItem>
					<Flex justify="flex-end" gap={ 2 }>
						{ activeFormat && (
							<FlexItem>
								<Button
									variant="tertiary"
									onClick={ removeTooltip }
									isDestructive
								>
									{ __( 'Remove', 'gatherpress' ) }
								</Button>
							</FlexItem>
						) }
						<FlexItem>
							<Button variant="secondary" onClick={ onClose }>
								{ __( 'Cancel', 'gatherpress' ) }
							</Button>
						</FlexItem>
						<FlexItem>
							<Button variant="primary" onClick={ applyTooltip }>
								{ __( 'Apply', 'gatherpress' ) }
							</Button>
						</FlexItem>
					</Flex>
				</FlexItem>
			</Flex>
		</Popover>
	);
}

/**
 * Tooltip format edit component.
 *
 * This component renders the toolbar button and popover for adding/editing tooltips.
 * The tooltip button is disabled when the selected text is inside a link.
 *
 * @since 1.0.0
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.value      RichText value object.
 * @param {Function} props.onChange   Callback when value changes.
 * @param {boolean}  props.isActive   Whether the format is currently active.
 * @param {Object}   props.contentRef Reference to the content element.
 *
 * @return {JSX.Element|null} The edit component or null if inside a link.
 */
export function TooltipEdit( { value, onChange, isActive, contentRef } ) {
	const [ isPopoverVisible, setIsPopoverVisible ] = useState( false );
	const activeFormat = getActiveFormat( value, FORMAT_NAME );
	const cachedActiveFormat = useCachedTruthy( activeFormat );

	// Check if text has link format applied - disable tooltip for inline links.
	const hasLinkFormat = !! getActiveFormat( value, 'core/link' );

	const togglePopover = useCallback( () => {
		setIsPopoverVisible( ( prev ) => ! prev );
	}, [] );

	const closePopover = useCallback( () => {
		setIsPopoverVisible( false );
	}, [] );

	// Don't render the button if text has an inline link format.
	if ( hasLinkFormat ) {
		return null;
	}

	return (
		<>
			<RichTextToolbarButton
				icon={ comment }
				title={ __( 'Tooltip', 'gatherpress' ) }
				onClick={ togglePopover }
				isActive={ isActive }
			/>
			{ isPopoverVisible && (
				<TooltipPopover
					onClose={ closePopover }
					value={ value }
					onChange={ onChange }
					activeFormat={ cachedActiveFormat }
					contentRef={ contentRef }
				/>
			) }
		</>
	);
}
