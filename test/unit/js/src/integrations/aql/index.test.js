/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import {
	describe,
	expect,
	it,
	jest,
	beforeAll,
	beforeEach,
} from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Mock WordPress dependencies
 */
jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => (
		<div data-testid="inspector-controls">{ children }</div>
	),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children, title } ) => (
		<div data-testid="panel-body" data-title={ title }>
			{ children }
		</div>
	),
} ) );

jest.mock( '@wordpress/element', () => ( {
	useEffect: jest.fn( ( fn ) => fn() ),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

/**
 * Mock internal dependencies
 */
jest.mock( '@src/helpers/event', () => ( {
	isPostTypeSupporting: jest.fn(),
} ) );

jest.mock( '@src/variations/core/query/components', () => ( {
	EventListTypeControls: () => (
		<div data-testid="event-list-type-controls" />
	),
	EventIncludeUnfinishedControls: () => (
		<div data-testid="event-include-unfinished-controls" />
	),
	EventOrderControls: () => <div data-testid="event-order-controls" />,
} ) );

/**
 * Internal dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { useEffect } from '@wordpress/element';
import { isPostTypeSupporting } from '@src/helpers/event';

// Import the module to trigger the addFilter side effect.
import '@src/integrations/aql/index';

describe( 'AQL Integration', () => {
	let withAQLEventControls;

	// Capture the HOC reference from the module-level addFilter call.
	beforeAll( () => {
		withAQLEventControls = addFilter.mock.calls[ 0 ][ 2 ];
	} );

	beforeEach( () => {
		useEffect.mockClear();
		// Restore default useEffect behavior for each test.
		useEffect.mockImplementation( ( fn ) => fn() );
		// Default: return true only for gatherpress_event post type.
		isPostTypeSupporting.mockImplementation(
			( support, postType ) =>
				'gatherpress-event-date' === support &&
				'gatherpress_event' === postType
		);
	} );

	describe( 'addFilter registration', () => {
		it( 'registers the editor.BlockEdit filter with correct namespace', () => {
			expect( addFilter ).toHaveBeenCalledWith(
				'editor.BlockEdit',
				'gatherpress/aql-integration',
				expect.any( Function )
			);
		} );
	} );

	describe( 'withAQLEventControls HOC', () => {
		it( 'renders only BlockEdit for non-query blocks', () => {
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			const { getByTestId, queryByTestId } = render(
				<Enhanced
					name="core/paragraph"
					attributes={ { namespace: 'some-namespace' } }
				/>
			);

			expect( getByTestId( 'block-edit' ) ).toBeInTheDocument();
			expect(
				queryByTestId( 'inspector-controls' )
			).not.toBeInTheDocument();
		} );

		it( 'renders only BlockEdit for non-AQL query blocks', () => {
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			const { getByTestId, queryByTestId } = render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'gatherpress-event-query',
						query: { postType: 'gatherpress_event' },
					} }
				/>
			);

			expect( getByTestId( 'block-edit' ) ).toBeInTheDocument();
			expect(
				queryByTestId( 'inspector-controls' )
			).not.toBeInTheDocument();
		} );

		it( 'renders event controls panel for AQL blocks with gatherpress_event post type', () => {
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			const { getByTestId } = render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: { postType: 'gatherpress_event' },
					} }
					setAttributes={ jest.fn() }
				/>
			);

			expect( getByTestId( 'block-edit' ) ).toBeInTheDocument();
			expect(
				getByTestId( 'inspector-controls' )
			).toBeInTheDocument();
			expect( getByTestId( 'panel-body' ) ).toHaveAttribute(
				'data-title',
				'Event Query Settings'
			);
			expect(
				getByTestId( 'event-list-type-controls' )
			).toBeInTheDocument();
			expect(
				getByTestId( 'event-include-unfinished-controls' )
			).toBeInTheDocument();
			expect(
				getByTestId( 'event-order-controls' )
			).toBeInTheDocument();
		} );

		it( 'renders event controls panel for AQL blocks with custom event-date-supporting post type', () => {
			// Mock gatherpress_shindig as a post type that supports gatherpress-event-date.
			isPostTypeSupporting.mockImplementation(
				( support, postType ) =>
					'gatherpress-event-date' === support &&
					'gatherpress_shindig' === postType
			);

			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			const { getByTestId } = render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: { postType: 'gatherpress_shindig' },
					} }
					setAttributes={ jest.fn() }
				/>
			);

			expect( getByTestId( 'block-edit' ) ).toBeInTheDocument();
			expect( getByTestId( 'inspector-controls' ) ).toBeInTheDocument();
			expect( getByTestId( 'event-list-type-controls' ) ).toBeInTheDocument();
		} );

		it( 'renders BlockEdit without controls for AQL blocks with non-event post type', () => {
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			const { getByTestId, queryByTestId } = render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: { postType: 'post' },
					} }
					setAttributes={ jest.fn() }
				/>
			);

			expect( getByTestId( 'block-edit' ) ).toBeInTheDocument();
			expect(
				queryByTestId( 'inspector-controls' )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'AQLEventDefaults auto-defaults', () => {
		it( 'sets GatherPress defaults when post type supports event-date and event query is not set', () => {
			const mockSetAttributes = jest.fn();
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: { postType: 'gatherpress_event' },
					} }
					setAttributes={ mockSetAttributes }
				/>
			);

			expect( mockSetAttributes ).toHaveBeenCalledWith( {
				query: {
					postType: 'gatherpress_event',
					gatherpress_event_query: 'upcoming',
					include_unfinished: 1,
					order: 'asc',
					orderBy: 'datetime',
				},
			} );
		} );

		it( 'does not set defaults when gatherpress_event_query is already set', () => {
			const mockSetAttributes = jest.fn();
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: {
							postType: 'gatherpress_event',
							gatherpress_event_query: 'past',
						},
					} }
					setAttributes={ mockSetAttributes }
				/>
			);

			expect( mockSetAttributes ).not.toHaveBeenCalled();
		} );

		it( 'does not set defaults for non-event post types in AQL blocks', () => {
			const mockSetAttributes = jest.fn();
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: { postType: 'post' },
					} }
					setAttributes={ mockSetAttributes }
				/>
			);

			expect( mockSetAttributes ).not.toHaveBeenCalled();
		} );

		it( 'preserves existing query attributes when setting defaults', () => {
			const mockSetAttributes = jest.fn();
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: {
							postType: 'gatherpress_event',
							perPage: 10,
							offset: 5,
						},
					} }
					setAttributes={ mockSetAttributes }
				/>
			);

			expect( mockSetAttributes ).toHaveBeenCalledWith( {
				query: {
					postType: 'gatherpress_event',
					perPage: 10,
					offset: 5,
					gatherpress_event_query: 'upcoming',
					include_unfinished: 1,
					order: 'asc',
					orderBy: 'datetime',
				},
			} );
		} );

		it( 'calls useEffect with correct dependencies', () => {
			const mockSetAttributes = jest.fn();
			const MockBlockEdit = () => (
				<div data-testid="block-edit">Original</div>
			);
			const Enhanced = withAQLEventControls( MockBlockEdit );

			render(
				<Enhanced
					name="core/query"
					attributes={ {
						namespace: 'advanced-query-loop',
						query: { postType: 'gatherpress_event' },
					} }
					setAttributes={ mockSetAttributes }
				/>
			);

			// Verify useEffect was called with a callback and dependency array.
			expect( useEffect ).toHaveBeenCalledWith(
				expect.any( Function ),
				expect.arrayContaining( [
					'gatherpress_event',
					undefined,
					expect.any( Object ),
					mockSetAttributes,
				] )
			);
		} );
	} );
} );
