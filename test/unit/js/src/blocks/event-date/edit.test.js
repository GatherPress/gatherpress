/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { render, fireEvent } from '@testing-library/react';

/**
 * Mocks
 */
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( () => ( {
		dateTimeStart: '2026-08-01 18:00:00',
		dateTimeEnd: '2026-08-01 20:00:00',
		timezone: 'UTC',
		isLoading: false,
		isValidEvent: true,
	} ) ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	BlockControls: ( { children } ) => <div>{ children }</div>,
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	useBlockProps: jest.fn( () => ( {} ) ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalVStack: ( { children } ) => <div>{ children }</div>,
	// What core's ExternalLink renders, so the test sees what the link does.
	ExternalLink: ( { href, children } ) => (
		<a href={ href } target="_blank" rel="external noreferrer noopener">
			{ children }
			<span aria-label="(opens in a new tab)">&#8599;</span>
		</a>
	),
	PanelBody: ( { children } ) => <div>{ children }</div>,
	RadioControl: () => null,
	Spinner: () => <div>spinner</div>,
	// Rendered rather than stubbed so the separator control's value and
	// placeholder can be asserted. aria-label rather than a wrapping <label>,
	// which getByLabelText reads just the same without tripping
	// jsx-a11y/label-has-associated-control on a mock.
	TextControl: ( { label, value, placeholder, onChange } ) => (
		<input
			type="text"
			aria-label={ label }
			value={ value }
			placeholder={ placeholder }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
	ToggleControl: ( { label, help, checked, onChange } ) => (
		<>
			<button
				aria-pressed={ checked }
				onClick={ () => onChange( ! checked ) }
			>
				{ label }
			</button>
			{ help && <p>{ help }</p> }
		</>
	),
	ToolbarButton: ( { text } ) => <button>{ text }</button>,
	ToolbarGroup: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '@src/components/DateTimeRange', () => () => null );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromSettings: ( key ) =>
		( {
			dateFormat: 'F j, Y',
			timeFormat: 'g:i a',
			showTimezone: false,
		} )[ key ],
} ) );

jest.mock( '@src/helpers/event', () => ( {
	isEventPostType: () => false,
	DISABLED_FIELD_OPACITY: 0.5,
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: () => false,
} ) );

jest.mock( '@src/helpers/datetime', () => {
	const actualMoment = jest.requireActual( 'moment' );

	return {
		convertPHPToMomentFormat: () => 'YYYY-MM-DD HH:mm',
		createMomentWithTimezone: ( dateTime ) => actualMoment( dateTime ),
		getTimezone: () => 'UTC',
		getUtcOffset: () => '',
		isManualOffset: () => false,
		removeNonTimePHPFormatChars: ( format ) => format,
	};
} );

jest.mock( '@src/blocks/event-date/helpers', () => ( {
	resolveEventDateData: jest.fn(),
} ) );

/**
 * Internal dependencies
 */
import Edit from '@src/blocks/event-date/edit';

const baseAttributes = {
	displayType: 'both',
	isLink: false,
	startDateFormat: '',
	endDateFormat: '',
	separator: 'to',
	showTimezone: '',
};

const renderEdit = ( attributes = {}, setAttributes = jest.fn() ) =>
	render(
		<Edit
			attributes={ { ...baseAttributes, ...attributes } }
			setAttributes={ setAttributes }
			context={ {} }
		/>,
	);

describe( 'Event Date Edit isLink', () => {
	it( 'renders the datetime without a link by default', () => {
		const { container } = renderEdit();

		expect(
			container.querySelector( 'a[href="#gatherpress-event-date-pseudo-link"]' ),
		).toBeNull();
	} );

	it( 'wraps the datetime in a pseudo-link when isLink is set', () => {
		const { container } = renderEdit( { isLink: true } );

		const anchor = container.querySelector(
			'a[href="#gatherpress-event-date-pseudo-link"]',
		);

		expect( anchor ).not.toBeNull();
		expect( anchor.textContent ).not.toBe( '' );
	} );

	it( 'prevents navigation when the pseudo-link is clicked', () => {
		const { container } = renderEdit( { isLink: true } );

		const anchor = container.querySelector(
			'a[href="#gatherpress-event-date-pseudo-link"]',
		);

		// fireEvent returns false when preventDefault was called.
		expect( fireEvent.click( anchor ) ).toBe( false );
	} );

	it( 'toggles the isLink attribute from the Link to event control', () => {
		const setAttributes = jest.fn();
		const { getByText } = renderEdit( {}, setAttributes );

		fireEvent.click( getByText( 'Link to event' ) );

		expect( setAttributes ).toHaveBeenCalledWith( { isLink: true } );
	} );

	it( 'toggles the isLink attribute back off', () => {
		const setAttributes = jest.fn();
		const { getByText } = renderEdit( { isLink: true }, setAttributes );

		fireEvent.click( getByText( 'Link to event' ) );

		expect( setAttributes ).toHaveBeenCalledWith( { isLink: false } );
	} );

	it( 'describes what the Link to event toggle does', () => {
		const { getByText } = renderEdit();

		expect(
			getByText( 'Make the date a link to the event page.' ),
		).toBeInTheDocument();
	} );
} );

describe( 'Event Date Edit documentation link', () => {
	it( 'opens the formatting documentation in a new tab and says so', () => {
		const { getByRole } = renderEdit();
		const link = getByRole( 'link', {
			name: /Date\/time formatting documentation/,
		} );

		expect( link.getAttribute( 'href' ) ).toBe(
			'https://wordpress.org/documentation/article/customize-date-and-time-format/',
		);
		expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( link.getAttribute( 'rel' ) ).toContain( 'noopener' );
		expect(
			getByRole( 'link', { name: /opens in a new tab/ } ),
		).toBe( link );
	} );
} );

describe( 'Event Date Edit separator control', () => {
	it( 'offers the localized default as a placeholder when unset', () => {
		const { getByLabelText } = renderEdit( { separator: '' } );
		const input = getByLabelText( 'Separator' );

		expect( input ).toHaveValue( '' );
		expect( input ).toHaveAttribute( 'placeholder', 'to' );
	} );

	it( 'reads a legacy "to" as unset so the placeholder still shows', () => {
		const { getByLabelText } = renderEdit( { separator: 'to' } );
		const input = getByLabelText( 'Separator' );

		expect( input ).toHaveValue( '' );
		expect( input ).toHaveAttribute( 'placeholder', 'to' );
	} );

	it( 'shows a custom separator as it was saved', () => {
		const { getByLabelText } = renderEdit( { separator: 'UNTIL' } );

		expect( getByLabelText( 'Separator' ) ).toHaveValue( 'UNTIL' );
	} );

	it( 'reports what is typed into the separator field', () => {
		const setAttributes = jest.fn();
		const { getByLabelText } = renderEdit( { separator: 'to' }, setAttributes );

		fireEvent.change( getByLabelText( 'Separator' ), {
			target: { value: 'bis' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( { separator: 'bis' } );
	} );
} );
