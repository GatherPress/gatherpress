/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { SelectControl, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getFromConfig } from '../helpers/editor-settings';

/**
 * The value the "Custom" option carries.
 *
 * Mirrors `Settings\Format_Field::CUSTOM` on the PHP side, and for the same
 * reason: an empty format already means "inherit the site default" here, so
 * Custom needs a value of its own rather than borrowing one that means
 * something else.
 *
 * @since TBD
 *
 * @type {string}
 */
export const FORMAT_CUSTOM = '__gatherpress_custom__';

/**
 * The formats offered in the editor, as examples.
 *
 * Both lists are offered together because a block is often one half of a pair:
 * a template puts the date on one line and the time under it. Which list an
 * entry came from does not need saying, since the rendered example says it.
 *
 * @since TBD
 *
 * @return {Array<{format: string, example: string}>} The choices.
 */
export function getFormatChoices() {
	return [
		...( getFromConfig( 'dateFormatChoices' ) || [] ),
		...( getFromConfig( 'timeFormatChoices' ) || [] ),
	];
}

/**
 * Choose a date/time format by the date it produces.
 *
 * The block editor counterpart of the `format` settings field: a list of
 * rendered examples, a Custom escape hatch holding a raw PHP format, and the
 * empty value kept as a first-class choice so a block can go back to
 * inheriting whatever the site settings say.
 *
 * @since TBD
 *
 * @param {Object}   props              - Component props.
 * @param {string}   props.label        - The control's label.
 * @param {string}   props.value        - The current PHP format, or '' to inherit.
 * @param {string}   props.inheritLabel - Label for the inherit-the-site-default option.
 * @param {Function} props.onChange     - Called with the new format.
 *
 * @return {JSX.Element} The rendered React component.
 */
const FormatControl = ( { label, value, inheritLabel, onChange } ) => {
	const choices = getFormatChoices();
	const isListed = choices.some( ( choice ) => choice.format === value );

	// State carries only the explicit "Custom" choice, which is what keeps the
	// select from snapping back to inherit before anything has been typed.
	// Everything else is derived, so a value arriving later, from an undo or
	// another setAttributes, still opens the field holding it.
	const [ customChosen, setCustomChosen ] = useState( false );
	const isCustom = customChosen || ( !! value && ! isListed );

	const options = [
		{ label: inheritLabel, value: '' },
		...choices.map( ( choice ) => ( {
			label: choice.example,
			value: choice.format,
		} ) ),
		{ label: __( 'Custom…', 'gatherpress' ), value: FORMAT_CUSTOM },
	];

	const onSelect = ( selected ) => {
		if ( FORMAT_CUSTOM === selected ) {
			setCustomChosen( true );
			return;
		}

		setCustomChosen( false );
		onChange( selected );
	};

	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				label={ label }
				value={ isCustom ? FORMAT_CUSTOM : value }
				options={ options }
				onChange={ onSelect }
			/>
			{ isCustom && (
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Custom format', 'gatherpress' ) }
					value={ value }
					onChange={ onChange }
				/>
			) }
		</>
	);
};

export default FormatControl;
