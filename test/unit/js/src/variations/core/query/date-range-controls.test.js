/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/components', () => ( {
	RangeControl: () => null,
	SelectControl: ( { label, help, value, options, onChange } ) => (
		<>
			<label htmlFor="date-range">{ label }</label>
			<select
				id="date-range"
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
			<p>{ help }</p>
		</>
	),
	TextControl: ( { label, value, min, max, onChange } ) => (
		<>
			<label htmlFor={ label }>{ label }</label>
			<input
				id={ label }
				value={ value }
				data-min={ min }
				data-max={ max }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</>
	),
	ToggleControl: () => null,
	__experimentalToggleGroupControl: () => null,
	__experimentalToggleGroupControlOption: () => null,
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	_x: ( text ) => text,
	sprintf: ( text ) => text,
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

jest.mock( '@src/variations/core/query/slots/query-controls', () => ( {
	__esModule: true,
	default: () => null,
} ) );

jest.mock( '@src/variations/core/query/slots/inherited-query-controls', () => ( {
	__esModule: true,
	default: () => null,
} ) );

jest.mock( '@src/helpers/event', () => ( {
	isEventPostType: jest.fn(),
	isPostTypeSupporting: jest.fn(),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: jest.fn(),
	usePostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
} ) );

/**
 * Internal dependencies
 */
import { EventDateRangeControls } from '@src/variations/core/query/components';

/**
 * Render the control and return the setAttributes mock.
 *
 * @param {Object} query The block's query attribute.
 *
 * @return {Function} The setAttributes mock.
 */
const renderControl = ( query ) => {
	const setAttributes = jest.fn();

	render(
		<EventDateRangeControls
			attributes={ { query } }
			setAttributes={ setAttributes }
		/>,
	);

	return setAttributes;
};

describe( 'EventDateRangeControls', () => {
	it( 'defaults to any time and hides the custom dates', () => {
		renderControl( { postType: 'gatherpress_event' } );

		expect( screen.getByLabelText( 'Date Range' ) ).toHaveValue( '' );
		expect( screen.queryByLabelText( 'Start Date' ) ).not.toBeInTheDocument();
		expect( screen.queryByLabelText( 'End Date' ) ).not.toBeInTheDocument();
		expect(
			screen.getByText(
				'Combines with the list type, so Upcoming with This year shows the rest of this year.',
			),
		).toBeInTheDocument();
	} );

	it( 'renders with no query attribute at all', () => {
		render(
			<EventDateRangeControls attributes={ {} } setAttributes={ jest.fn() } />,
		);

		expect( screen.getByLabelText( 'Date Range' ) ).toHaveValue( '' );
	} );

	it( 'saves a preset and drops any custom dates', () => {
		const setAttributes = renderControl( {
			postType: 'gatherpress_event',
			gatherpress_date_range: 'custom',
			gatherpress_date_after: '2026-01-01',
			gatherpress_date_before: '2026-01-31',
		} );

		fireEvent.change( screen.getByLabelText( 'Date Range' ), {
			target: { value: 'next_month' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_event',
				gatherpress_date_range: 'next_month',
			},
		} );
	} );

	it( 'removes the range when switching back to any time', () => {
		const setAttributes = renderControl( {
			postType: 'gatherpress_event',
			gatherpress_date_range: 'this_year',
		} );

		fireEvent.change( screen.getByLabelText( 'Date Range' ), {
			target: { value: '' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: { postType: 'gatherpress_event' },
		} );
	} );

	it( 'keeps saved dates when choosing the custom range', () => {
		const setAttributes = renderControl( {
			gatherpress_date_after: '2026-01-01',
		} );

		fireEvent.change( screen.getByLabelText( 'Date Range' ), {
			target: { value: 'custom' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				gatherpress_date_range: 'custom',
				gatherpress_date_after: '2026-01-01',
			},
		} );
	} );

	it( 'shows the custom dates, each limited by the other', () => {
		renderControl( {
			gatherpress_date_range: 'custom',
			gatherpress_date_after: '2026-01-01',
			gatherpress_date_before: '2026-01-31',
		} );

		const start = screen.getByLabelText( 'Start Date' );
		const end = screen.getByLabelText( 'End Date' );

		expect( start ).toHaveValue( '2026-01-01' );
		expect( start ).toHaveAttribute( 'data-max', '2026-01-31' );
		expect( end ).toHaveValue( '2026-01-31' );
		expect( end ).toHaveAttribute( 'data-min', '2026-01-01' );
	} );

	it( 'leaves the custom dates open when only one is set', () => {
		renderControl( { gatherpress_date_range: 'custom' } );

		expect( screen.getByLabelText( 'Start Date' ) ).not.toHaveAttribute( 'data-max' );
		expect( screen.getByLabelText( 'End Date' ) ).not.toHaveAttribute( 'data-min' );
	} );

	it( 'saves a start date and an end date', () => {
		const setAttributes = renderControl( { gatherpress_date_range: 'custom' } );

		fireEvent.change( screen.getByLabelText( 'Start Date' ), {
			target: { value: '2026-03-01' },
		} );
		fireEvent.change( screen.getByLabelText( 'End Date' ), {
			target: { value: '2026-03-31' },
		} );

		expect( setAttributes ).toHaveBeenNthCalledWith( 1, {
			query: {
				gatherpress_date_range: 'custom',
				gatherpress_date_after: '2026-03-01',
			},
		} );
		expect( setAttributes ).toHaveBeenNthCalledWith( 2, {
			query: {
				gatherpress_date_range: 'custom',
				gatherpress_date_before: '2026-03-31',
			},
		} );
	} );

	it( 'removes a cleared date', () => {
		const setAttributes = renderControl( {
			gatherpress_date_range: 'custom',
			gatherpress_date_after: '2026-03-01',
		} );

		fireEvent.change( screen.getByLabelText( 'Start Date' ), {
			target: { value: '' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: { gatherpress_date_range: 'custom' },
		} );
	} );
} );
