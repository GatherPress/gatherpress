/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
import { useCachedTruthy } from '@wordpress/block-editor';
import {
	getActiveFormat,
	applyFormat,
	removeFormat,
} from '@wordpress/rich-text';

// Mock WordPress modules.
jest.mock( '@wordpress/block-editor', () => ( {
	RichTextToolbarButton: ( { title, onClick, isActive } ) => (
		<button
			data-testid="tooltip-button"
			onClick={ onClick }
			data-active={ isActive ? 'true' : 'false' }
		>
			{ title }
		</button>
	),
	useCachedTruthy: jest.fn( ( value ) => value ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Popover: ( { children, onClose } ) => (
		<div data-testid="popover">
			{ children }
			<button data-testid="close-popover" onClick={ onClose }>
				Close
			</button>
		</div>
	),
	TextControl: ( { label, value, onChange, placeholder } ) => (
		<input
			data-testid="text-control"
			aria-label={ label }
			value={ value }
			onChange={ ( e ) => onChange( e.target.value ) }
			placeholder={ placeholder }
		/>
	),
	ColorPicker: ( { color, onChange } ) => (
		<input
			data-testid="color-picker"
			type="color"
			value={ color }
			onChange={ ( e ) => onChange( e.target.value ) }
		/>
	),
	Button: ( { children, onClick, variant } ) => (
		<button data-testid={ `button-${ variant }` } onClick={ onClick }>
			{ children }
		</button>
	),
	Flex: ( { children } ) => <div data-testid="flex">{ children }</div>,
	FlexItem: ( { children } ) => (
		<div data-testid="flex-item">{ children }</div>
	),
} ) );

jest.mock( '@wordpress/rich-text', () => ( {
	applyFormat: jest.fn( ( value, format ) => ( {
		...value,
		appliedFormat: format,
	} ) ),
	removeFormat: jest.fn( ( value, formatName ) => ( {
		...value,
		removed: true,
		removedFormat: formatName,
	} ) ),
	getActiveFormat: jest.fn(),
	useAnchor: jest.fn( () => ( { getBoundingClientRect: () => ( {} ) } ) ),
} ) );

jest.mock( '@wordpress/icons', () => ( {
	comment: 'comment-icon',
} ) );

// Import after mocks.
import { TooltipEdit } from '../../../../../../src/formats/tooltip/edit';

describe( 'TooltipEdit component', () => {
	const mockOnChange = jest.fn();
	const mockContentRef = { current: document.createElement( 'div' ) };
	const defaultProps = {
		value: { text: 'test', start: 0, end: 4 },
		onChange: mockOnChange,
		isActive: false,
		contentRef: mockContentRef,
	};

	beforeEach( () => {
		jest.clearAllMocks();
		getActiveFormat.mockReturnValue( null );
	} );

	it( 'renders the toolbar button', () => {
		render( <TooltipEdit { ...defaultProps } /> );

		expect( screen.getByTestId( 'tooltip-button' ) ).toBeTruthy();
	} );

	it( 'shows tooltip title on button', () => {
		render( <TooltipEdit { ...defaultProps } /> );

		expect( screen.getByTestId( 'tooltip-button' ).textContent ).toBe(
			'Tooltip'
		);
	} );

	it( 'button reflects isActive state when false', () => {
		render( <TooltipEdit { ...defaultProps } isActive={ false } /> );

		expect(
			screen.getByTestId( 'tooltip-button' ).getAttribute( 'data-active' )
		).toBe( 'false' );
	} );

	it( 'button reflects isActive state when true', () => {
		render( <TooltipEdit { ...defaultProps } isActive={ true } /> );

		expect(
			screen.getByTestId( 'tooltip-button' ).getAttribute( 'data-active' )
		).toBe( 'true' );
	} );

	it( 'opens popover when button is clicked', () => {
		render( <TooltipEdit { ...defaultProps } /> );

		expect( screen.queryByTestId( 'popover' ) ).toBeNull();

		fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

		expect( screen.getByTestId( 'popover' ) ).toBeTruthy();
	} );

	it( 'closes popover when close button is clicked', () => {
		render( <TooltipEdit { ...defaultProps } /> );

		fireEvent.click( screen.getByTestId( 'tooltip-button' ) );
		expect( screen.getByTestId( 'popover' ) ).toBeTruthy();

		fireEvent.click( screen.getByTestId( 'close-popover' ) );
		expect( screen.queryByTestId( 'popover' ) ).toBeNull();
	} );

	it( 'returns null when text has link format', () => {
		// First call for tooltip format (returns null), second for link format.
		getActiveFormat
			.mockReturnValueOnce( null )
			.mockReturnValueOnce( { type: 'core/link' } );

		const { container } = render( <TooltipEdit { ...defaultProps } /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'renders when text does not have link format', () => {
		getActiveFormat.mockReturnValue( null );

		render( <TooltipEdit { ...defaultProps } /> );

		expect( screen.getByTestId( 'tooltip-button' ) ).toBeTruthy();
	} );

	it( 'uses useCachedTruthy for active format', () => {
		const activeFormat = {
			type: 'gatherpress/tooltip',
			attributes: { 'data-gatherpress-tooltip': 'Test' },
		};
		getActiveFormat.mockReturnValue( activeFormat );

		render( <TooltipEdit { ...defaultProps } /> );

		expect( useCachedTruthy ).toHaveBeenCalledWith( activeFormat );
	} );

	describe( 'Popover interactions', () => {
		it( 'shows text input in popover', () => {
			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.getByTestId( 'text-control' ) ).toBeTruthy();
		} );

		it( 'shows apply button in popover', () => {
			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.getByText( 'Apply' ) ).toBeTruthy();
		} );

		it( 'shows cancel button in popover', () => {
			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.getByText( 'Cancel' ) ).toBeTruthy();
		} );

		it( 'shows remove button when format is active', () => {
			const activeFormat = {
				type: 'gatherpress/tooltip',
				attributes: { 'data-gatherpress-tooltip': 'Existing tooltip' },
			};
			// Mock based on format name argument.
			getActiveFormat.mockImplementation( ( val, formatName ) => {
				if ( 'gatherpress/tooltip' === formatName ) {
					return activeFormat;
				}
				// Return null for 'core/link' check.
				return null;
			} );

			render( <TooltipEdit { ...defaultProps } isActive={ true } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.getByText( 'Remove' ) ).toBeTruthy();
		} );

		it( 'does not show remove button when no active format', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.queryByText( 'Remove' ) ).toBeNull();
		} );

		it( 'calls applyFormat when Apply button is clicked with text', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// Enter tooltip text.
			fireEvent.change( screen.getByTestId( 'text-control' ), {
				target: { value: 'Test tooltip' },
			} );

			// Click Apply.
			fireEvent.click( screen.getByText( 'Apply' ) );

			expect( applyFormat ).toHaveBeenCalledWith(
				defaultProps.value,
				expect.objectContaining( {
					type: 'gatherpress/tooltip',
					attributes: expect.objectContaining( {
						'data-gatherpress-tooltip': 'Test tooltip',
					} ),
				} )
			);
			expect( mockOnChange ).toHaveBeenCalled();
		} );

		it( 'calls removeFormat when Apply is clicked with empty text', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// Leave text empty and click Apply.
			fireEvent.click( screen.getByText( 'Apply' ) );

			expect( removeFormat ).toHaveBeenCalledWith(
				defaultProps.value,
				'gatherpress/tooltip'
			);
			expect( mockOnChange ).toHaveBeenCalled();
		} );

		it( 'calls removeFormat when Remove button is clicked', () => {
			const activeFormat = {
				type: 'gatherpress/tooltip',
				attributes: { 'data-gatherpress-tooltip': 'Existing' },
			};
			getActiveFormat.mockImplementation( ( val, formatName ) => {
				if ( 'gatherpress/tooltip' === formatName ) {
					return activeFormat;
				}
				return null;
			} );

			render( <TooltipEdit { ...defaultProps } isActive={ true } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );
			fireEvent.click( screen.getByText( 'Remove' ) );

			expect( removeFormat ).toHaveBeenCalledWith(
				defaultProps.value,
				'gatherpress/tooltip'
			);
			expect( mockOnChange ).toHaveBeenCalled();
		} );

		it( 'shows color picker buttons', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.getByText( 'Text Color' ) ).toBeTruthy();
			expect( screen.getByText( 'Background' ) ).toBeTruthy();
		} );

		it( 'shows preview section', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			expect( screen.getByText( 'Preview:' ) ).toBeTruthy();
			expect( screen.getByText( 'Hover me' ) ).toBeTruthy();
		} );

		it( 'closes popover when Cancel button is clicked', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );
			expect( screen.getByTestId( 'popover' ) ).toBeTruthy();

			fireEvent.click( screen.getByText( 'Cancel' ) );
			expect( screen.queryByTestId( 'popover' ) ).toBeNull();
		} );

		it( 'includes text color in attributes when different from default', () => {
			const activeFormat = {
				type: 'gatherpress/tooltip',
				attributes: {
					'data-gatherpress-tooltip': 'Test',
					'data-gatherpress-tooltip-text-color': '#ff0000',
				},
			};
			getActiveFormat.mockImplementation( ( val, formatName ) => {
				if ( 'gatherpress/tooltip' === formatName ) {
					return activeFormat;
				}
				return null;
			} );

			render( <TooltipEdit { ...defaultProps } isActive={ true } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// Enter tooltip text.
			fireEvent.change( screen.getByTestId( 'text-control' ), {
				target: { value: 'Custom color tooltip' },
			} );

			// Click Apply.
			fireEvent.click( screen.getByText( 'Apply' ) );

			// Verify applyFormat was called with custom text color.
			expect( applyFormat ).toHaveBeenCalledWith(
				defaultProps.value,
				expect.objectContaining( {
					type: 'gatherpress/tooltip',
					attributes: expect.objectContaining( {
						'data-gatherpress-tooltip': 'Custom color tooltip',
						'data-gatherpress-tooltip-text-color': '#ff0000',
					} ),
				} )
			);
		} );

		it( 'includes bg color in attributes when different from default', () => {
			const activeFormat = {
				type: 'gatherpress/tooltip',
				attributes: {
					'data-gatherpress-tooltip': 'Test',
					'data-gatherpress-tooltip-bg-color': '#00ff00',
				},
			};
			getActiveFormat.mockImplementation( ( val, formatName ) => {
				if ( 'gatherpress/tooltip' === formatName ) {
					return activeFormat;
				}
				return null;
			} );

			render( <TooltipEdit { ...defaultProps } isActive={ true } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// Enter tooltip text.
			fireEvent.change( screen.getByTestId( 'text-control' ), {
				target: { value: 'Custom bg tooltip' },
			} );

			// Click Apply.
			fireEvent.click( screen.getByText( 'Apply' ) );

			// Verify applyFormat was called with custom bg color.
			expect( applyFormat ).toHaveBeenCalledWith(
				defaultProps.value,
				expect.objectContaining( {
					type: 'gatherpress/tooltip',
					attributes: expect.objectContaining( {
						'data-gatherpress-tooltip': 'Custom bg tooltip',
						'data-gatherpress-tooltip-bg-color': '#00ff00',
					} ),
				} )
			);
		} );

		it( 'toggles text color picker when Text Color button is clicked', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// Initially no color picker visible.
			expect( screen.queryByTestId( 'color-picker' ) ).toBeNull();

			// Click Text Color button.
			fireEvent.click( screen.getByText( 'Text Color' ) );

			// Color picker should be visible now.
			expect( screen.getByTestId( 'color-picker' ) ).toBeTruthy();

			// Click Text Color button again to hide.
			fireEvent.click( screen.getByText( 'Text Color' ) );

			// Color picker should be hidden.
			expect( screen.queryByTestId( 'color-picker' ) ).toBeNull();
		} );

		it( 'toggles background color picker when Background button is clicked', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// Initially no color picker visible.
			expect( screen.queryByTestId( 'color-picker' ) ).toBeNull();

			// Click Background button.
			fireEvent.click( screen.getByText( 'Background' ) );

			// Color picker should be visible now.
			expect( screen.getByTestId( 'color-picker' ) ).toBeTruthy();
		} );

		it( 'hides text color picker when Background button is clicked', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// First show text color picker.
			fireEvent.click( screen.getByText( 'Text Color' ) );
			expect( screen.getByTestId( 'color-picker' ) ).toBeTruthy();

			// Then click Background - should switch to bg color picker.
			fireEvent.click( screen.getByText( 'Background' ) );

			// Color picker should still be visible (but for bg now).
			expect( screen.getByTestId( 'color-picker' ) ).toBeTruthy();
		} );

		it( 'hides bg color picker when Text Color button is clicked', () => {
			getActiveFormat.mockReturnValue( null );

			render( <TooltipEdit { ...defaultProps } /> );

			fireEvent.click( screen.getByTestId( 'tooltip-button' ) );

			// First show bg color picker.
			fireEvent.click( screen.getByText( 'Background' ) );
			expect( screen.getByTestId( 'color-picker' ) ).toBeTruthy();

			// Then click Text Color - should switch to text color picker.
			fireEvent.click( screen.getByText( 'Text Color' ) );

			// Color picker should still be visible (but for text now).
			expect( screen.getByTestId( 'color-picker' ) ).toBeTruthy();
		} );
	} );
} );
