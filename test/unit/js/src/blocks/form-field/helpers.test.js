/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	getInputStyles,
	getLabelStyles,
	getLabelWrapperStyles,
	getOptionStyles,
	getWrapperClasses,
} from '../../../../../../src/blocks/form-field/helpers';

describe( 'Form field helper functions', () => {
	describe( 'getInputStyles', () => {
		it( 'returns basic opacity style for all field types', () => {
			const result = getInputStyles( 'text', {} );

			expect( result ).toHaveProperty( 'opacity', 1 );
		} );

		it( 'applies font and text styles for text-based inputs', () => {
			const attributes = {
				inputFontSize: '16px',
				inputLineHeight: '1.5',
				inputPadding: 10,
				inputBorderWidth: 2,
				inputBorderRadius: 5,
				fieldWidth: 75,
				fieldTextColor: '#333',
				fieldBackgroundColor: '#fff',
				borderColor: '#ccc',
			};

			const result = getInputStyles( 'text', attributes );

			expect( result.fontSize ).toBe( '16px' );
			expect( result.lineHeight ).toBe( '1.5' );
			expect( result.color ).toBe( '#333' );
			expect( result.backgroundColor ).toBe( '#fff' );
			expect( result.padding ).toBe( '10px' );
			expect( result.borderRadius ).toBe( '5px' );
			expect( result.width ).toBe( '75%' );
			expect( result.borderWidth ).toBe( '2px' );
			expect( result.borderColor ).toBe( '#ccc' );
			expect( result.cursor ).toBe( 'text' );
		} );

		it( 'does not apply text styles for checkbox inputs', () => {
			const attributes = {
				inputFontSize: '16px',
				inputPadding: 10,
			};

			const result = getInputStyles( 'checkbox', attributes );

			expect( result.fontSize ).toBeUndefined();
			expect( result.padding ).toBeUndefined();
			expect( result.cursor ).toBe( 'default' );
			expect( result.opacity ).toBe( 1 );
		} );

		it( 'does not apply text styles for radio inputs', () => {
			const attributes = {
				inputFontSize: '16px',
				inputPadding: 10,
			};

			const result = getInputStyles( 'radio', attributes );

			expect( result.fontSize ).toBeUndefined();
			expect( result.padding ).toBeUndefined();
			expect( result.cursor ).toBe( 'default' );
			expect( result.opacity ).toBe( 1 );
		} );

		it( 'does not apply text styles for hidden inputs', () => {
			const attributes = {
				inputFontSize: '16px',
				inputPadding: 10,
			};

			const result = getInputStyles( 'hidden', attributes );

			expect( result.fontSize ).toBeUndefined();
			expect( result.padding ).toBeUndefined();
			expect( result.cursor ).toBe( 'default' );
		} );

		it( 'applies transparent background when no background color is set for text inputs', () => {
			const result = getInputStyles( 'text', {} );

			expect( result.backgroundColor ).toBe( 'transparent' );
		} );

		it( 'applies inherit color when no text color is set', () => {
			const result = getInputStyles( 'text', {} );

			expect( result.color ).toBe( 'inherit' );
		} );

		it( 'does not override background color when set', () => {
			const attributes = {
				fieldBackgroundColor: '#f0f0f0',
			};

			const result = getInputStyles( 'text', attributes );

			expect( result.backgroundColor ).toBe( '#f0f0f0' );
		} );

		it( 'handles textarea field type with text styles', () => {
			const attributes = {
				inputFontSize: '14px',
				inputLineHeight: '1.6',
			};

			const result = getInputStyles( 'textarea', attributes );

			expect( result.fontSize ).toBe( '14px' );
			expect( result.lineHeight ).toBe( '1.6' );
			expect( result.cursor ).toBe( 'text' );
		} );
	} );

	describe( 'getLabelStyles', () => {
		it( 'returns cursor text style by default', () => {
			const result = getLabelStyles( {} );

			expect( result.cursor ).toBe( 'text' );
		} );

		it( 'applies label text color when provided', () => {
			const attributes = {
				labelTextColor: '#666',
			};

			const result = getLabelStyles( attributes );

			expect( result.color ).toBe( '#666' );
			expect( result.cursor ).toBe( 'text' );
		} );

		it( 'does not set color when labelTextColor is not provided', () => {
			const result = getLabelStyles( {} );

			expect( result.color ).toBeUndefined();
		} );
	} );

	describe( 'getLabelWrapperStyles', () => {
		it( 'returns empty object when no attributes provided', () => {
			const result = getLabelWrapperStyles( {} );

			expect( Object.keys( result ).length ).toBe( 0 );
		} );

		it( 'applies font size when provided', () => {
			const attributes = {
				labelFontSize: '18px',
			};

			const result = getLabelWrapperStyles( attributes );

			expect( result.fontSize ).toBe( '18px' );
		} );

		it( 'applies line height when provided', () => {
			const attributes = {
				labelLineHeight: '1.8',
			};

			const result = getLabelWrapperStyles( attributes );

			expect( result.lineHeight ).toBe( '1.8' );
		} );

		it( 'applies both font size and line height', () => {
			const attributes = {
				labelFontSize: '16px',
				labelLineHeight: '1.5',
			};

			const result = getLabelWrapperStyles( attributes );

			expect( result.fontSize ).toBe( '16px' );
			expect( result.lineHeight ).toBe( '1.5' );
		} );
	} );

	describe( 'getOptionStyles', () => {
		it( 'returns cursor text style by default', () => {
			const result = getOptionStyles( {} );

			expect( result.cursor ).toBe( 'text' );
		} );

		it( 'applies option font size when provided', () => {
			const attributes = {
				optionFontSize: '14px',
			};

			const result = getOptionStyles( attributes );

			expect( result.fontSize ).toBe( '14px' );
		} );

		it( 'applies option line height when provided', () => {
			const attributes = {
				optionLineHeight: '1.4',
			};

			const result = getOptionStyles( attributes );

			expect( result.lineHeight ).toBe( '1.4' );
		} );

		it( 'applies option text color when provided', () => {
			const attributes = {
				optionTextColor: '#444',
			};

			const result = getOptionStyles( attributes );

			expect( result.color ).toBe( '#444' );
		} );

		it( 'applies all option styles together', () => {
			const attributes = {
				optionFontSize: '15px',
				optionLineHeight: '1.6',
				optionTextColor: '#555',
			};

			const result = getOptionStyles( attributes );

			expect( result.fontSize ).toBe( '15px' );
			expect( result.lineHeight ).toBe( '1.6' );
			expect( result.color ).toBe( '#555' );
			expect( result.cursor ).toBe( 'text' );
		} );
	} );

	describe( 'getWrapperClasses', () => {
		it( 'returns basic field type class', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'text', blockProps, false );

			expect( result ).toBe( 'gatherpress-form-field--text' );
		} );

		it( 'includes blockProps className if provided', () => {
			const blockProps = { className: 'custom-class' };

			const result = getWrapperClasses( 'text', blockProps, false );

			expect( result ).toBe( 'custom-class gatherpress-form-field--text' );
		} );

		it( 'adds inline layout class for text fields when inlineLayout is true', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'text', blockProps, true );

			expect( result ).toContain( 'gatherpress-inline-layout' );
			expect( result ).toContain( 'gatherpress-form-field--text' );
		} );

		it( 'does not add inline layout class for checkbox fields', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'checkbox', blockProps, true );

			expect( result ).not.toContain( 'gatherpress-inline-layout' );
			expect( result ).toBe( 'gatherpress-form-field--checkbox' );
		} );

		it( 'does not add inline layout class for radio fields', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'radio', blockProps, true );

			expect( result ).not.toContain( 'gatherpress-inline-layout' );
		} );

		it( 'does not add inline layout class for hidden fields', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'hidden', blockProps, true );

			expect( result ).not.toContain( 'gatherpress-inline-layout' );
		} );

		it( 'does not add inline layout class for textarea fields', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'textarea', blockProps, true );

			expect( result ).not.toContain( 'gatherpress-inline-layout' );
		} );

		it( 'adds inline layout class for email fields when inlineLayout is true', () => {
			const blockProps = { className: '' };

			const result = getWrapperClasses( 'email', blockProps, true );

			expect( result ).toContain( 'gatherpress-inline-layout' );
		} );

		it( 'handles undefined className in blockProps', () => {
			const blockProps = {};

			const result = getWrapperClasses( 'text', blockProps, false );

			expect( result ).toBe( 'gatherpress-form-field--text' );
		} );

		it( 'trims whitespace from final class string', () => {
			const blockProps = { className: '  extra-spaces  ' };

			const result = getWrapperClasses( 'text', blockProps, false );

			// Should not have leading/trailing spaces.
			expect( result ).toBe( result.trim() );
		} );
	} );
} );
