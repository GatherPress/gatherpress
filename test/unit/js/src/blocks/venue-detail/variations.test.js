/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import variations from '../../../../../../src/blocks/venue-detail/variations';

describe( 'venue-detail variations', () => {
	it( 'exports an array of variations', () => {
		expect( Array.isArray( variations ) ).toBe( true );
		expect( variations.length ).toBe( 3 );
	} );

	describe( 'venue-address variation', () => {
		const addressVariation = variations.find(
			( v ) => 'venue-address' === v.name
		);

		it( 'has correct name and title', () => {
			expect( addressVariation.name ).toBe( 'venue-address' );
			expect( addressVariation.title ).toBe( 'Venue Address' );
		} );

		it( 'has correct description', () => {
			expect( addressVariation.description ).toBe(
				'Display the venue address.'
			);
		} );

		it( 'has location icon', () => {
			expect( addressVariation.icon ).toBe( 'location' );
		} );

		it( 'is the default variation', () => {
			expect( addressVariation.isDefault ).toBe( true );
		} );

		it( 'has correct attributes', () => {
			expect( addressVariation.attributes.placeholder ).toBe(
				'Venue address…'
			);
			expect( addressVariation.attributes.fieldType ).toBe( 'address' );
		} );

		it( 'has correct scope', () => {
			expect( addressVariation.scope ).toEqual( [
				'inserter',
				'transform',
			] );
		} );
	} );

	describe( 'venue-phone variation', () => {
		const phoneVariation = variations.find(
			( v ) => 'venue-phone' === v.name
		);

		it( 'has correct name and title', () => {
			expect( phoneVariation.name ).toBe( 'venue-phone' );
			expect( phoneVariation.title ).toBe( 'Venue Phone' );
		} );

		it( 'has correct description', () => {
			expect( phoneVariation.description ).toBe(
				'Display the venue phone number.'
			);
		} );

		it( 'has phone icon', () => {
			expect( phoneVariation.icon ).toBe( 'phone' );
		} );

		it( 'is not the default variation', () => {
			expect( phoneVariation.isDefault ).toBeUndefined();
		} );

		it( 'has correct attributes', () => {
			expect( phoneVariation.attributes.placeholder ).toBe(
				'Venue phone…'
			);
			expect( phoneVariation.attributes.fieldType ).toBe( 'phone' );
		} );

		it( 'has correct scope', () => {
			expect( phoneVariation.scope ).toEqual( [
				'inserter',
				'transform',
			] );
		} );
	} );

	describe( 'venue-website variation', () => {
		const websiteVariation = variations.find(
			( v ) => 'venue-website' === v.name
		);

		it( 'has correct name and title', () => {
			expect( websiteVariation.name ).toBe( 'venue-website' );
			expect( websiteVariation.title ).toBe( 'Venue Website' );
		} );

		it( 'has correct description', () => {
			expect( websiteVariation.description ).toBe(
				'Display the venue website URL.'
			);
		} );

		it( 'has admin-links icon', () => {
			expect( websiteVariation.icon ).toBe( 'admin-links' );
		} );

		it( 'is not the default variation', () => {
			expect( websiteVariation.isDefault ).toBeUndefined();
		} );

		it( 'has correct attributes', () => {
			expect( websiteVariation.attributes.placeholder ).toBe(
				'Venue website…'
			);
			expect( websiteVariation.attributes.fieldType ).toBe( 'url' );
		} );

		it( 'has correct scope', () => {
			expect( websiteVariation.scope ).toEqual( [
				'inserter',
				'transform',
			] );
		} );
	} );
} );
