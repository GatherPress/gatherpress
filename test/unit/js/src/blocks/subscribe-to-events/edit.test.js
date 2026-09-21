/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Mock state driving the core-data queries.
 */
const mockState = {
	topics: [],
	selected: null,
	isResolving: false,
	venueOptions: [ { value: 9, label: 'City Library' } ],
	eventPostTypes: [ 'gatherpress_event' ],
};

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( ( callback ) => {
		const wpSelect = () => ( {
			getEntityRecords: () => mockState.topics,
			getEntityRecord: () => mockState.selected,
			isResolving: () => mockState.isResolving,
		} );

		return callback( wpSelect );
	} ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '@wordpress/compose', () => ( {
	useDebounce: ( fn ) => fn,
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	useBlockProps: jest.fn( () => ( { className: 'wp-block' } ) ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalVStack: ( { children } ) => <div>{ children }</div>,
	PanelBody: ( { title, children } ) => (
		<div>
			<span>{ title }</span>
			{ children }
		</div>
	),
	// Radio controls surface their options as buttons so the test can click a scope.
	RadioControl: ( { label, selected, options, onChange } ) => (
		<fieldset>
			<legend>{ label }</legend>
			{ options.map( ( option ) => (
				<button
					key={ option.value }
					aria-pressed={ selected === option.value }
					onClick={ () => onChange( option.value ) }
				>
					{ option.label }
				</button>
			) ) }
		</fieldset>
	),
	SelectControl: ( { label, value, options, onChange } ) => (
		<div>
			<span>{ label }</span>
			<select
				aria-label={ label }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
		</div>
	),
	TextControl: ( { label, value, onChange } ) => (
		<div>
			<span>{ label }</span>
			<input
				aria-label={ label }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</div>
	),
	// Surfaces the label, the resolved value, and the option labels the block passed in.
	ComboboxControl: ( { label, value, options, onChange } ) => (
		<div>
			<span>{ label }</span>
			<span data-testid="combobox-value">{ value ?? '' }</span>
			{ options.map( ( option ) => (
				<button
					key={ option.value }
					onClick={ () => onChange( String( option.value ) ) }
				>
					{ `${ label }:${ option.label }` }
				</button>
			) ) }
			<button onClick={ () => onChange( null ) }>
				{ `${ label }:clear` }
			</button>
		</div>
	),
} ) );

jest.mock( '@wordpress/server-side-render', () => ( {
	__esModule: true,
	ServerSideRender: ( { block, attributes, skipBlockSupportAttributes } ) => (
		<div
			data-testid="ssr-preview"
			data-block={ block }
			data-skip-supports={ JSON.stringify( skipBlockSupportAttributes ) }
		>
			{ JSON.stringify( attributes ) }
		</div>
	),
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: ( key ) =>
		'eventPostTypes' === key ? mockState.eventPostTypes : undefined,
} ) );
jest.mock( '@src/helpers/venue', () => ( {
	getVenuePostType: () => 'gatherpress_venue',
	useVenueOptions: ( search ) => ( {
		venueOptions: mockState.venueOptions,
		search,
	} ),
} ) );

jest.mock( '@src/components/TopicSelect', () => ( {
	__esModule: true,
	default: ( { value, onChange } ) => (
		<div>
			<span>topic picker</span>
			<span data-testid="topic-value">{ value ?? '' }</span>
			<button onClick={ () => onChange( 42 ) }>pick topic</button>
			<button onClick={ () => onChange( null ) }>clear topic</button>
		</div>
	),
} ) );

/**
 * Internal dependencies
 */
import Edit from '@src/blocks/subscribe-to-events/edit';

/**
 * Renders the edit component with the given attributes.
 *
 * @param {Object} attributes Attribute overrides.
 *
 * @return {Object} The setAttributes mock and render result.
 */
function renderEdit( attributes = {} ) {
	const setAttributes = jest.fn();

	const result = render(
		<Edit
			attributes={ {
				scope: 'sitewide',
				postType: '',
				venueId: 0,
				topicId: 0,
				linkFormat: 'both',
				subscribeText: '',
				linkText: '',
				...attributes,
			} }
			setAttributes={ setAttributes }
		/>,
	);

	return { setAttributes, ...result };
}

describe( 'Subscribe to Events edit', () => {
	it( 'previews the block through ServerSideRender', () => {
		renderEdit();

		expect( screen.getByTestId( 'ssr-preview' ) ).toBeInTheDocument();
	} );

	// `useBlockProps()` already puts the block supports on the editor wrapper,
	// and the server puts them on the <ul>, so the preview must not send them
	// again or they land twice in the editor and once on the front end.
	it( 'skips the block support attributes in the preview', () => {
		renderEdit();

		expect(
			screen.getByTestId( 'ssr-preview' ),
		).toHaveAttribute( 'data-skip-supports', 'true' );
	} );

	it( 'updates the scope attribute when another scope is chosen', () => {
		const { setAttributes } = renderEdit();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Events in one topic' } ),
		);

		expect( setAttributes ).toHaveBeenCalledWith( { scope: 'topic' } );
	} );

	it( 'shows the topic picker only for the topic scope', () => {
		const { unmount } = renderEdit( { scope: 'topic' } );
		expect( screen.getByText( 'topic picker' ) ).toBeInTheDocument();
		unmount();

		renderEdit( { scope: 'sitewide' } );
		expect( screen.queryByText( 'topic picker' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the venue picker only for the venue scope', () => {
		const { unmount } = renderEdit( { scope: 'venue' } );
		expect( screen.getByText( 'Venue' ) ).toBeInTheDocument();
		unmount();

		renderEdit( { scope: 'sitewide' } );
		expect( screen.queryByText( 'Venue' ) ).not.toBeInTheDocument();
	} );

	it( 'falls back to an empty post type option list when config is missing', () => {
		mockState.eventPostTypes = null;

		renderEdit( { scope: 'archive' } );

		// The archive panel still renders; the select has only its Default entry.
		expect( screen.getByText( 'Which events?' ) ).toBeInTheDocument();
		expect(
			screen.getAllByRole( 'option' ).map( ( option ) => option.value ),
		).toEqual( [ '' ] );

		mockState.eventPostTypes = [ 'gatherpress_event' ];
	} );

	it( 'passes the block name and attributes to ServerSideRender', () => {
		renderEdit( { scope: 'venue', venueId: 7 } );

		const preview = screen.getByTestId( 'ssr-preview' );

		expect( preview ).toHaveAttribute(
			'data-block',
			'gatherpress/subscribe-to-events',
		);
		expect( preview ).toHaveTextContent( '"venueId":7' );
	} );

	it( 'shows the archive post type select only for the archive scope', () => {
		const { unmount } = renderEdit( { scope: 'archive' } );
		expect( screen.getByLabelText( 'Event archive' ) ).toBeInTheDocument();
		unmount();

		renderEdit( { scope: 'sitewide' } );
		expect( screen.queryByLabelText( 'Event archive' ) ).not.toBeInTheDocument();
	} );

	it( 'lists the configured event post types in the archive select', () => {
		mockState.eventPostTypes = [ 'gatherpress_event', 'gatherpress_conference' ];

		renderEdit( { scope: 'archive' } );

		expect(
			screen.getByRole( 'option', { name: 'gatherpress_conference' } ),
		).toBeInTheDocument();

		mockState.eventPostTypes = [ 'gatherpress_event' ];
	} );

	it( 'updates the post type attribute from the archive select', () => {
		const { setAttributes } = renderEdit( { scope: 'archive' } );

		fireEvent.change( screen.getByLabelText( 'Event archive' ), {
			target: { value: 'gatherpress_event' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( {
			postType: 'gatherpress_event',
		} );
	} );

	it( 'updates the venue attribute and clears it to zero', () => {
		const { setAttributes } = renderEdit( { scope: 'venue' } );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Venue:City Library' } ),
		);

		expect( setAttributes ).toHaveBeenCalledWith( { venueId: 9 } );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Venue:clear' } ),
		);

		expect( setAttributes ).toHaveBeenCalledWith( { venueId: 0 } );
	} );

	it( 'updates the topic attribute and clears it to zero', () => {
		const { setAttributes } = renderEdit( { scope: 'topic', topicId: 42 } );

		fireEvent.click( screen.getByText( 'pick topic' ) );

		expect( setAttributes ).toHaveBeenCalledWith( { topicId: 42 } );

		fireEvent.click( screen.getByText( 'clear topic' ) );

		expect( setAttributes ).toHaveBeenCalledWith( { topicId: 0 } );
	} );

	it( 'updates the link format attribute', () => {
		const { setAttributes } = renderEdit();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Subscribe link only' } ),
		);

		expect( setAttributes ).toHaveBeenCalledWith( { linkFormat: 'webcal' } );
	} );

	it( 'updates both link text attributes', () => {
		const { setAttributes } = renderEdit();

		fireEvent.change( screen.getByLabelText( 'iCal link text' ), {
			target: { value: 'Feed' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( { linkText: 'Feed' } );

		fireEvent.change( screen.getByLabelText( 'Subscribe link text' ), {
			target: { value: 'Follow us' },
		} );

		expect( setAttributes ).toHaveBeenCalledWith( {
			subscribeText: 'Follow us',
		} );
	} );
} );
