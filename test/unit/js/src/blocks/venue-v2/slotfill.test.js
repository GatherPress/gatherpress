/**
 * External dependencies.
 */
import { render } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Mock WordPress components.
 */
jest.mock( '@wordpress/components', () => ( {
	Fill: ( { children, name } ) => (
		<div data-testid="fill" data-name={ name }>
			{ children }
		</div>
	),
} ) );

/**
 * Mock internal dependencies.
 */
jest.mock( '../../../../../../src/components/VenueNavigator', () => {
	return function MockVenueNavigator() {
		return <div data-testid="venue-navigator">VenueNavigator</div>;
	};
} );

jest.mock( '../../../../../../src/helpers/event', () => ( {
	isEventPostType: jest.fn(),
} ) );

/**
 * Internal dependencies.
 */
import VenueBlockPluginFill from '../../../../../../src/blocks/venue-v2/slotfill';
import { isEventPostType } from '../../../../../../src/helpers/event';

describe( 'VenueBlockPluginFill', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render Fill with VenueNavigator when isEventPostType returns true', () => {
		isEventPostType.mockReturnValue( true );

		const { getByTestId } = render( <VenueBlockPluginFill /> );

		// Check that Fill is rendered with correct name.
		const fill = getByTestId( 'fill' );
		expect( fill ).toBeInTheDocument();
		expect( fill ).toHaveAttribute( 'data-name', 'VenuePluginDocumentSettings' );

		// Check that VenueNavigator is rendered inside Fill.
		const venueNavigator = getByTestId( 'venue-navigator' );
		expect( venueNavigator ).toBeInTheDocument();
		expect( fill ).toContainElement( venueNavigator );
	} );

	it( 'should return null when isEventPostType returns false', () => {
		isEventPostType.mockReturnValue( false );

		const { container } = render( <VenueBlockPluginFill /> );

		// Component should render nothing.
		expect( container.firstChild ).toBeNull();
	} );

	it( 'should call isEventPostType on render', () => {
		isEventPostType.mockReturnValue( true );

		render( <VenueBlockPluginFill /> );

		expect( isEventPostType ).toHaveBeenCalled();
	} );
} );
