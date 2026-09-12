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
	TextControl: () => null,
	ToggleControl: ( { label, help, checked, onChange, disabled } ) => (
		<>
			<button
				aria-pressed={ checked }
				disabled={ disabled }
				onClick={ () => ! disabled && onChange( ! checked ) }
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

const mockSettings = {
	dateFormat: 'F j, Y',
	timeFormat: 'g:i a',
	showTimezone: false,
	showViewerTimezone: true,
};

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromSettings: ( key ) => mockSettings[ key ],
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
		/>
	);

describe( 'Event Date Edit isLink', () => {
	it( 'renders the datetime without a link by default', () => {
		const { container } = renderEdit();

		expect(
			container.querySelector( 'a[href="#gatherpress-event-date-pseudo-link"]' )
		).toBeNull();
	} );

	it( 'wraps the datetime in a pseudo-link when isLink is set', () => {
		const { container } = renderEdit( { isLink: true } );

		const anchor = container.querySelector(
			'a[href="#gatherpress-event-date-pseudo-link"]'
		);

		expect( anchor ).not.toBeNull();
		expect( anchor.textContent ).not.toBe( '' );
	} );

	it( 'prevents navigation when the pseudo-link is clicked', () => {
		const { container } = renderEdit( { isLink: true } );

		const anchor = container.querySelector(
			'a[href="#gatherpress-event-date-pseudo-link"]'
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
			getByText( 'Make the date a link to the event page.' )
		).toBeInTheDocument();
	} );
} );

describe( 'Event Date Edit displayType', () => {
	it( 'reads an empty displayType as both, the way render.php does', () => {
		// Unreachable through the UI, since block.json defaults displayType to
		// "both", but hand-authored and migrated markup can carry the empty
		// string. `Event::get_display_datetime()` renders a full range for it,
		// so the editor has to preview one or the author is shown something no
		// viewer will get.
		const { container } = renderEdit( { displayType: '' } );

		expect( container.textContent ).toContain(
			'2026-08-01 18:00 to 2026-08-01 20:00'
		);
	} );
} );

describe( 'Event Date Edit showViewerTime', () => {
	it( 'renders the toggle disabled when timezone is not appended', () => {
		const { getByText } = renderEdit( { showTimezone: 'no' } );
		const toggle = getByText( 'Show viewer local time' );

		expect( toggle ).toBeDefined();
		expect( toggle.hasAttribute( 'disabled' ) ).toBe( true );
	} );

	it( 'renders the toggle enabled when timezone is appended', () => {
		const setAttributes = jest.fn();
		const { getByText } = renderEdit(
			{ showTimezone: 'yes', showViewerTime: false },
			setAttributes
		);
		const toggle = getByText( 'Show viewer local time' );

		expect( toggle.hasAttribute( 'disabled' ) ).toBe( false );
		fireEvent.click( toggle );
		expect( setAttributes ).toHaveBeenCalledWith( { showViewerTime: true } );
	} );

	it( 'does not render the toggle when showViewerTimezone is disabled globally', () => {
		mockSettings.showViewerTimezone = false;
		const { queryByText } = renderEdit( { showTimezone: 'yes' } );

		expect( queryByText( 'Show viewer local time' ) ).toBeNull();
		mockSettings.showViewerTimezone = true;
	} );
} );

describe( 'Event Date Edit documentation link', () => {
	it( 'opens the formatting documentation in a new tab and says so', () => {
		const { getByRole } = renderEdit();
		const link = getByRole( 'link', {
			name: /Date\/time formatting documentation/,
		} );

		expect( link.getAttribute( 'href' ) ).toBe(
			'https://wordpress.org/documentation/article/customize-date-and-time-format/'
		);
		expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( link.getAttribute( 'rel' ) ).toContain( 'noopener' );
		expect(
			getByRole( 'link', { name: /opens in a new tab/ } )
		).toBe( link );
	} );
} );
