/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { render, renderHook, screen, fireEvent } from '@testing-library/react';

/**
 * Mock state driving the core-data queries.
 */
const mockState = {
	records: [],
	selected: null,
	isResolving: false,
	queries: [],
	selectedLookups: [],
};

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( ( callback ) => {
		const wpSelect = () => ( {
			getEntityRecords: ( kind, name, query ) => {
				mockState.queries.push( { kind, name, query } );
				return mockState.records;
			},
			getEntityRecord: ( kind, name, id ) => {
				mockState.selectedLookups.push( { kind, name, id } );
				return mockState.selected;
			},
			isResolving: () => mockState.isResolving,
		} );

		return callback( wpSelect );
	} ),
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

jest.mock( '@wordpress/html-entities', () => ( {
	decodeEntities: ( text ) => text,
} ) );

// The real @wordpress/components entry pulls in rich-text, which needs store
// plumbing this unit test does not set up. This stand-in exposes the pieces of
// the combobox the tests drive through the public props.
jest.mock( '@wordpress/components', () => ( {
	ComboboxControl: ( { label, value, options, onChange, onFilterValueChange } ) => (
		<div>
			<span>{ label }</span>
			<span data-testid="combobox-value">{ value ?? '' }</span>
			<input
				aria-label="filter"
				onChange={ ( event ) => onFilterValueChange( event.target.value ) }
			/>
			{ options.map( ( option ) => (
				<button
					key={ option.value }
					onClick={ () => onChange( String( option.value ) ) }
				>
					{ option.label }
				</button>
			) ) }
			<button onClick={ () => onChange( null ) }>clear</button>
		</div>
	),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	useDebounce: ( fn ) => fn,
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

/**
 * Internal dependencies
 */
import TopicSelect, {
	toTopicOptions,
	useTopicOptions,
	TOPIC_TAXONOMY,
} from '@src/components/TopicSelect';

describe( 'toTopicOptions', () => {
	it( 'maps term records to combobox options', () => {
		expect(
			toTopicOptions(
				[
					{ id: 1, name: 'Workshops' },
					{ id: 2, name: 'Meetups' },
				],
				null,
			),
		).toEqual( [
			{ value: 1, label: 'Workshops' },
			{ value: 2, label: 'Meetups' },
		] );
	} );

	it( 'falls back to the ID when a term has no name', () => {
		expect( toTopicOptions( [ { id: 7 } ], null ) ).toEqual( [
			{ value: 7, label: '#7' },
		] );
	} );

	it( 'returns no options for missing records', () => {
		expect( toTopicOptions( undefined, null ) ).toEqual( [] );
	} );

	it( 'keeps the current selection visible when the search excludes it', () => {
		const options = toTopicOptions(
			[ { id: 2, name: 'Meetups' } ],
			{ id: 1, name: 'Workshops' },
		);

		expect( options ).toEqual( [
			{ value: 1, label: 'Workshops' },
			{ value: 2, label: 'Meetups' },
		] );
	} );

	it( 'does not duplicate the selection when it is already listed', () => {
		const options = toTopicOptions(
			[ { id: 1, name: 'Workshops' } ],
			{ id: 1, name: 'Workshops' },
		);

		expect( options ).toEqual( [ { value: 1, label: 'Workshops' } ] );
	} );
} );

describe( 'useTopicOptions', () => {
	it( 'queries the topic taxonomy', () => {
		mockState.queries = [];

		renderHook( () => useTopicOptions( 'work', 0 ) );

		expect( mockState.queries[ 0 ].kind ).toBe( 'taxonomy' );
		expect( mockState.queries[ 0 ].name ).toBe( TOPIC_TAXONOMY );
		expect( mockState.queries[ 0 ].query ).toMatchObject( {
			context: 'view',
			search: 'work',
		} );
	} );

	// The terms endpoint has no `relevance` orderby, so `name` is used whether
	// or not there is a search term. `relevance` would come back as a 400.
	it( 'always orders by name', () => {
		mockState.queries = [];

		renderHook( () => useTopicOptions( 'work', 0 ) );

		expect( mockState.queries[ 0 ].query.orderby ).toBe( 'name' );

		mockState.queries = [];

		renderHook( () => useTopicOptions( '', 0 ) );

		expect( mockState.queries[ 0 ].query.orderby ).toBe( 'name' );
	} );

	it( 'reports the resolution state from the store', () => {
		mockState.isResolving = true;

		const { result } = renderHook( () => useTopicOptions( '', 0 ) );

		expect( result.current.isResolving ).toBe( true );

		mockState.isResolving = false;
	} );

	it( 'loads the record for an existing selection', () => {
		mockState.selectedLookups = [];
		mockState.selected = { id: 5, name: 'Workshops' };

		renderHook( () => useTopicOptions( '', 5 ) );

		expect( mockState.selectedLookups ).toContainEqual( {
			kind: 'taxonomy',
			name: TOPIC_TAXONOMY,
			id: 5,
		} );

		mockState.selected = null;
	} );

	it( 'skips the record lookup when nothing is selected', () => {
		mockState.selectedLookups = [];

		renderHook( () => useTopicOptions( '', 0 ) );

		expect( mockState.selectedLookups ).toHaveLength( 0 );
	} );

	it( 'falls back to no options when the records are not resolved', () => {
		mockState.records = null;

		const { result } = renderHook( () => useTopicOptions( '', 0 ) );

		expect( result.current.topicOptions ).toEqual( [] );

		mockState.records = [];
	} );
} );

describe( 'TopicSelect', () => {
	it( 'renders the selected topic and reports a new selection', () => {
		const onChange = jest.fn();
		mockState.records = [
			{ id: 1, name: 'Workshops' },
			{ id: 2, name: 'Meetups' },
		];
		mockState.selected = { id: 2, name: 'Meetups' };

		render( <TopicSelect value={ 2 } onChange={ onChange } /> );

		expect( screen.getByTestId( 'combobox-value' ) ).toHaveTextContent( '2' );

		fireEvent.click( screen.getByText( 'Workshops' ) );

		expect( onChange ).toHaveBeenCalledWith( 1 );

		fireEvent.click( screen.getByText( 'clear' ) );
		expect( onChange ).toHaveBeenCalledWith( null );
	} );

	it( 'reports a cleared selection as null', () => {
		const onChange = jest.fn();
		mockState.records = [ { id: 1, name: 'Workshops' } ];
		mockState.selected = { id: 1, name: 'Workshops' };

		render( <TopicSelect value={ 1 } onChange={ onChange } /> );

		fireEvent.click( screen.getByText( 'clear' ) );

		expect( onChange ).toHaveBeenCalledWith( null );
	} );

	it( 're-queries with the typed filter term', () => {
		mockState.queries = [];
		mockState.records = [];
		mockState.selected = null;

		render( <TopicSelect value={ 0 } onChange={ jest.fn() } /> );

		fireEvent.change( screen.getByLabelText( 'filter' ), {
			target: { value: 'work' },
		} );

		expect(
			mockState.queries.some( ( { query } ) => 'work' === query.search ),
		).toBe( true );
	} );
} );
