/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { shouldHideBlock } from '../../../../../../src/blocks/rsvp-form/visibility';

describe( 'RSVP Form Visibility', () => {
	describe( 'shouldHideBlock', () => {
		describe( 'onSuccess: "show" behavior', () => {
			it( 'hides block in default state', () => {
				const visibility = { onSuccess: 'show' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe( true );
			} );

			it( 'shows block in success state', () => {
				const visibility = { onSuccess: 'show' };
				expect( shouldHideBlock( visibility, 'success' ) ).toBe( false );
			} );

			it( 'hides block in past state (when no whenPast setting)', () => {
				const visibility = { onSuccess: 'show' };
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( true );
			} );
		} );

		describe( 'onSuccess: "hide" behavior', () => {
			it( 'shows block in default state', () => {
				const visibility = { onSuccess: 'hide' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe( false );
			} );

			it( 'hides block in success state', () => {
				const visibility = { onSuccess: 'hide' };
				expect( shouldHideBlock( visibility, 'success' ) ).toBe( true );
			} );

			it( 'shows block in past state (when no whenPast setting)', () => {
				const visibility = { onSuccess: 'hide' };
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( false );
			} );
		} );

		describe( 'whenPast: "show" behavior (no onSuccess)', () => {
			it( 'hides block in default state', () => {
				const visibility = { whenPast: 'show' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe( true );
			} );

			it( 'hides block in success state', () => {
				const visibility = { whenPast: 'show' };
				expect( shouldHideBlock( visibility, 'success' ) ).toBe( true );
			} );

			it( 'shows block in past state', () => {
				const visibility = { whenPast: 'show' };
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( false );
			} );
		} );

		describe( 'whenPast: "hide" behavior (no onSuccess)', () => {
			it( 'shows block in default state', () => {
				const visibility = { whenPast: 'hide' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe( false );
			} );

			it( 'shows block in success state', () => {
				const visibility = { whenPast: 'hide' };
				expect( shouldHideBlock( visibility, 'success' ) ).toBe( false );
			} );

			it( 'hides block in past state', () => {
				const visibility = { whenPast: 'hide' };
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( true );
			} );
		} );

		describe( 'combined onSuccess + whenPast behavior', () => {
			describe( 'success message pattern: onSuccess: "show", whenPast: "hide"', () => {
				const visibility = { onSuccess: 'show', whenPast: 'hide' };

				it( 'hides in default state (not success yet)', () => {
					expect( shouldHideBlock( visibility, 'default' ) ).toBe(
						true
					);
				} );

				it( 'shows in success state', () => {
					expect( shouldHideBlock( visibility, 'success' ) ).toBe(
						false
					);
				} );

				it( 'hides in past state (whenPast takes precedence)', () => {
					expect( shouldHideBlock( visibility, 'past' ) ).toBe(
						true
					);
				} );
			} );

			describe( 'form field pattern: onSuccess: "hide", whenPast: "hide"', () => {
				const visibility = { onSuccess: 'hide', whenPast: 'hide' };

				it( 'shows in default state', () => {
					expect( shouldHideBlock( visibility, 'default' ) ).toBe(
						false
					);
				} );

				it( 'hides in success state (onSuccess applies)', () => {
					expect( shouldHideBlock( visibility, 'success' ) ).toBe(
						true
					);
				} );

				it( 'hides in past state (whenPast takes precedence)', () => {
					expect( shouldHideBlock( visibility, 'past' ) ).toBe(
						true
					);
				} );
			} );

			describe( 'whenPast precedence over onSuccess when past', () => {
				it( 'uses whenPast when past, ignoring onSuccess', () => {
					const visibility = { onSuccess: 'hide', whenPast: 'show' };
					expect( shouldHideBlock( visibility, 'past' ) ).toBe(
						false
					);
				} );

				it( 'uses onSuccess when not past, ignoring whenPast', () => {
					const visibility = { onSuccess: 'show', whenPast: 'hide' };
					expect( shouldHideBlock( visibility, 'default' ) ).toBe(
						true
					);
				} );
			} );
		} );

		describe( 'empty/default visibility settings', () => {
			it( 'shows block with no visibility settings', () => {
				const visibility = {};
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					false
				);
				expect( shouldHideBlock( visibility, 'success' ) ).toBe(
					false
				);
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( false );
			} );

			it( 'shows block with empty string values', () => {
				const visibility = { onSuccess: '', whenPast: '' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					false
				);
				expect( shouldHideBlock( visibility, 'success' ) ).toBe(
					false
				);
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( false );
			} );

			it( 'shows block with only onSuccess empty', () => {
				const visibility = { onSuccess: '' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					false
				);
			} );

			it( 'shows block with only whenPast empty', () => {
				const visibility = { whenPast: '' };
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					false
				);
			} );
		} );

		describe( 'real-world template scenarios', () => {
			it( 'success message block: shows after submission, hides when past', () => {
				const visibility = { onSuccess: 'show', whenPast: 'hide' };

				// Before submission - hidden.
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					true
				);

				// After submission - shown.
				expect( shouldHideBlock( visibility, 'success' ) ).toBe(
					false
				);

				// Event has passed - hidden (whenPast takes precedence).
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( true );
			} );

			it( 'form fields: hide after submission and when past', () => {
				const visibility = { onSuccess: 'hide', whenPast: 'hide' };

				// Before submission - shown.
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					false
				);

				// After submission - hidden.
				expect( shouldHideBlock( visibility, 'success' ) ).toBe(
					true
				);

				// Event has passed - hidden.
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( true );
			} );

			it( 'past event message: only shows when event has passed', () => {
				const visibility = { whenPast: 'show' };

				// Before event passes - hidden.
				expect( shouldHideBlock( visibility, 'default' ) ).toBe(
					true
				);

				// After submission but not past - hidden.
				expect( shouldHideBlock( visibility, 'success' ) ).toBe(
					true
				);

				// Event has passed - shown.
				expect( shouldHideBlock( visibility, 'past' ) ).toBe( false );
			} );
		} );
	} );
} );
