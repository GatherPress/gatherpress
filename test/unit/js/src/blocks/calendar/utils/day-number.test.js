/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	FALLBACK_BLOCK_GAP,
	findDayNumberBlock,
	getDayNumberJustifyContent,
	getLayoutProps,
	isDayNumberBindingBlock,
} from '@src/blocks/calendar/utils/day-number';

// Loading all of @wordpress/block-editor makes this suite very slow, and its
// package exports do not give access to the gap helper alone. This mock does
// what getGapCSSValue() and getSpacingPresetCssVar() do in
// @wordpress/block-editor (src/hooks/gap.js and
// src/components/spacing-sizes-control/utils.js): no value gives null, a
// spacing preset becomes its CSS variable, another string stays as it is, and
// an axial object gives "row column", or one value when both sides match.
jest.mock( '@wordpress/block-editor', () => {
	const getSpacingPresetCssVar = ( value ) => {
		if ( ! value ) {
			return undefined;
		}
		const slug = value.match( /var:preset\|spacing\|(.+)/ );
		return slug ? `var(--wp--preset--spacing--${ slug[ 1 ] })` : value;
	};

	return {
		__experimentalGetGapCSSValue: ( blockGapValue, defaultValue = '0' ) => {
			if ( ! blockGapValue ) {
				return null;
			}
			const isString = 'string' === typeof blockGapValue;
			const row =
				getSpacingPresetCssVar(
					isString ? blockGapValue : blockGapValue.top,
				) || defaultValue;
			const column =
				getSpacingPresetCssVar(
					isString ? blockGapValue : blockGapValue.left,
				) || defaultValue;

			return row === column ? row : `${ row } ${ column }`;
		},
	};
} );

/**
 * Build a block object.
 *
 * @param {string} name       Block name.
 * @param {Object} attributes Block attributes.
 *
 * @return {Object} Block object.
 */
const block = ( name, attributes = {} ) => ( {
	name,
	clientId: `${ name }-client-id`,
	attributes,
	innerBlocks: [],
} );

/**
 * Build a paragraph bound to a block bindings source.
 *
 * @param {string} source Binding source name.
 * @param {Object} style  Block style attribute.
 *
 * @return {Object} Paragraph block object.
 */
const boundParagraph = ( source, style = undefined ) =>
	block( 'core/paragraph', {
		metadata: {
			bindings: {
				content: { source },
			},
		},
		...( style ? { style } : {} ),
	} );

/**
 * Build the Day Number block with an optional text alignment.
 *
 * @param {string} textAlign Text alignment.
 *
 * @return {Object} Day Number block object.
 */
const dayNumber = ( textAlign = undefined ) =>
	boundParagraph(
		'gatherpress/calendar-day',
		textAlign ? { typography: { textAlign } } : undefined,
	);

describe( 'isDayNumberBindingBlock', () => {
	it( 'is true for a paragraph whose content is bound to the calendar day source', () => {
		expect( isDayNumberBindingBlock( dayNumber() ) ).toBe( true );
	} );

	it( 'is false for content bound to another source', () => {
		expect(
			isDayNumberBindingBlock( boundParagraph( 'core/post-meta' ) ),
		).toBe( false );
	} );

	it( 'is false for a block with no bindings, and for no block', () => {
		expect( isDayNumberBindingBlock( block( 'core/paragraph' ) ) ).toBe(
			false,
		);
		expect(
			isDayNumberBindingBlock( { name: 'core/paragraph' } ),
		).toBe( false );
		expect( isDayNumberBindingBlock( undefined ) ).toBe( false );
		expect( isDayNumberBindingBlock( null ) ).toBe( false );
	} );
} );

describe( 'findDayNumberBlock', () => {
	it( 'returns the Day Number block among the other inner blocks', () => {
		const day = dayNumber( 'center' );
		const blocks = [
			block( 'core/heading' ),
			boundParagraph( 'core/post-meta' ),
			day,
			block( 'gatherpress/calendar-entries' ),
		];

		expect( findDayNumberBlock( blocks ) ).toBe( day );
	} );

	it( 'returns the first Day Number block when there are two', () => {
		const first = dayNumber( 'left' );
		const second = dayNumber( 'right' );

		expect( findDayNumberBlock( [ first, second ] ) ).toBe( first );
	} );

	it( 'returns undefined when no inner block is the Day Number block', () => {
		expect(
			findDayNumberBlock( [ block( 'core/heading' ) ] ),
		).toBeUndefined();
		expect( findDayNumberBlock( [] ) ).toBeUndefined();
	} );

	it( 'returns undefined for missing inner blocks', () => {
		expect( findDayNumberBlock( undefined ) ).toBeUndefined();
		expect( findDayNumberBlock( null ) ).toBeUndefined();
	} );
} );

describe( 'getDayNumberJustifyContent', () => {
	it( 'turns the Day Number text alignment into the matching justify-content value', () => {
		expect( getDayNumberJustifyContent( [ dayNumber( 'left' ) ] ) ).toBe(
			'flex-start',
		);
		expect(
			getDayNumberJustifyContent( [ dayNumber( 'center' ) ] ),
		).toBe( 'center' );
		expect( getDayNumberJustifyContent( [ dayNumber( 'right' ) ] ) ).toBe(
			'flex-end',
		);
	} );

	it( 'reads the alignment of the Day Number block, not of the other blocks', () => {
		const blocks = [
			block( 'core/paragraph', {
				style: { typography: { textAlign: 'right' } },
			} ),
			dayNumber( 'center' ),
		];

		expect( getDayNumberJustifyContent( blocks ) ).toBe( 'center' );
	} );

	it( 'returns undefined to keep the CSS default when there is no alignment to follow', () => {
		// No text alignment on the Day Number block.
		expect( getDayNumberJustifyContent( [ dayNumber() ] ) ).toBeUndefined();
		// An alignment that has no justify-content equivalent.
		expect(
			getDayNumberJustifyContent( [ dayNumber( 'justify' ) ] ),
		).toBeUndefined();
		// No Day Number block.
		expect(
			getDayNumberJustifyContent( [ block( 'core/heading' ) ] ),
		).toBeUndefined();
		expect( getDayNumberJustifyContent( undefined ) ).toBeUndefined();
	} );
} );

describe( 'getLayoutProps', () => {
	describe( 'flow and constrained layouts', () => {
		it( 'uses the flow layout class and no styles when no layout is set', () => {
			expect( getLayoutProps() ).toEqual( {
				className: 'is-layout-flow',
				style: {},
			} );
			expect( getLayoutProps( null ) ).toEqual( {
				className: 'is-layout-flow',
				style: {},
			} );
			expect( getLayoutProps( { type: 'default' } ) ).toEqual( {
				className: 'is-layout-flow',
				style: {},
			} );
		} );

		it( 'uses the constrained layout class and no styles for a constrained layout', () => {
			expect(
				getLayoutProps( { type: 'constrained', contentSize: '40rem' } ),
			).toEqual( {
				className: 'is-layout-constrained',
				style: {},
			} );
		} );
	} );

	describe( 'flex layout', () => {
		it( 'gives a row with no options a wrapping flex display only', () => {
			expect( getLayoutProps( { type: 'flex' } ) ).toEqual( {
				className: 'is-layout-flex',
				style: { display: 'flex', flexWrap: 'wrap' },
			} );
		} );

		it( 'adds the orientation, justification and nowrap classes that core adds', () => {
			const { className } = getLayoutProps( {
				type: 'flex',
				orientation: 'horizontal',
				justifyContent: 'center',
				flexWrap: 'nowrap',
			} );

			expect( className ).toBe(
				'is-layout-flex is-horizontal is-content-justification-center is-nowrap',
			);
		} );

		it( 'sets justify-content and align-items for a row, including the row-only options', () => {
			expect(
				getLayoutProps( {
					type: 'flex',
					justifyContent: 'space-between',
					verticalAlignment: 'stretch',
					flexWrap: 'nowrap',
				} ).style,
			).toEqual( {
				display: 'flex',
				flexWrap: 'nowrap',
				justifyContent: 'space-between',
				alignItems: 'stretch',
			} );
			expect(
				getLayoutProps( {
					type: 'flex',
					orientation: 'horizontal',
					justifyContent: 'right',
					verticalAlignment: 'top',
				} ).style,
			).toEqual( {
				display: 'flex',
				flexWrap: 'wrap',
				justifyContent: 'flex-end',
				alignItems: 'flex-start',
			} );
		} );

		it( 'leaves out the stack-only options in a row, as core does', () => {
			const { className, style } = getLayoutProps( {
				type: 'flex',
				justifyContent: 'stretch',
				verticalAlignment: 'space-between',
			} );

			// The class still names the saved justification.
			expect( className ).toBe(
				'is-layout-flex is-content-justification-stretch',
			);
			expect( style ).toEqual( { display: 'flex', flexWrap: 'wrap' } );
		} );

		it( 'gives a stack a column direction and aligns its items to the start by default', () => {
			expect(
				getLayoutProps( { type: 'flex', orientation: 'vertical' } ),
			).toEqual( {
				className: 'is-layout-flex is-vertical',
				style: {
					display: 'flex',
					flexWrap: 'wrap',
					flexDirection: 'column',
					alignItems: 'flex-start',
				},
			} );
		} );

		it( 'swaps the axes in a stack: justification sets align-items, vertical alignment sets justify-content', () => {
			expect(
				getLayoutProps( {
					type: 'flex',
					orientation: 'vertical',
					justifyContent: 'right',
					verticalAlignment: 'bottom',
				} ).style,
			).toEqual( {
				display: 'flex',
				flexWrap: 'wrap',
				flexDirection: 'column',
				alignItems: 'flex-end',
				justifyContent: 'flex-end',
			} );
			expect(
				getLayoutProps( {
					type: 'flex',
					orientation: 'vertical',
					justifyContent: 'stretch',
					verticalAlignment: 'space-between',
				} ).style,
			).toEqual( {
				display: 'flex',
				flexWrap: 'wrap',
				flexDirection: 'column',
				alignItems: 'stretch',
				justifyContent: 'space-between',
			} );
		} );

		it( 'leaves out the row-only options in a stack, as core does', () => {
			expect(
				getLayoutProps( {
					type: 'flex',
					orientation: 'vertical',
					justifyContent: 'space-between',
					verticalAlignment: 'stretch',
				} ).style,
			).toEqual( {
				display: 'flex',
				flexWrap: 'wrap',
				flexDirection: 'column',
				alignItems: 'flex-start',
			} );
		} );
	} );

	describe( 'grid layout', () => {
		it( 'fills the row with columns of at least 12rem when no column setting is set', () => {
			expect( getLayoutProps( { type: 'grid' } ) ).toEqual( {
				className: 'is-layout-grid',
				style: {
					display: 'grid',
					gridTemplateColumns:
						'repeat(auto-fill, minmax(min(12rem, 100%), 1fr))',
					containerType: 'inline-size',
				},
			} );
		} );

		it( 'uses the minimum column width in Auto mode, and auto-fit when it is set', () => {
			expect(
				getLayoutProps( { type: 'grid', minimumColumnWidth: '8rem' } )
					.style,
			).toEqual( {
				display: 'grid',
				gridTemplateColumns:
					'repeat(auto-fill, minmax(min(8rem, 100%), 1fr))',
				containerType: 'inline-size',
			} );
			expect(
				getLayoutProps( {
					type: 'grid',
					minimumColumnWidth: '8rem',
					autoFit: true,
				} ).style.gridTemplateColumns,
			).toBe( 'repeat(auto-fit, minmax(min(8rem, 100%), 1fr))' );
		} );

		it( 'uses equal fixed columns in Manual mode, with no container query', () => {
			expect(
				getLayoutProps( { type: 'grid', columnCount: 3 } ).style,
			).toEqual( {
				display: 'grid',
				gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
			} );
		} );

		it( 'adds the row tracks only when both the column count and the row count are set', () => {
			expect(
				getLayoutProps( { type: 'grid', columnCount: 3, rowCount: 2 } )
					.style,
			).toEqual( {
				display: 'grid',
				gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
				gridTemplateRows: 'repeat(2, minmax(1rem, auto))',
			} );
			expect(
				getLayoutProps( { type: 'grid', rowCount: 2 } ).style
					.gridTemplateRows,
			).toBeUndefined();
		} );

		it( 'subtracts the theme block gap from the columns when both column settings are set and the block has no gap', () => {
			expect(
				getLayoutProps( {
					type: 'grid',
					columnCount: 4,
					minimumColumnWidth: '10rem',
				} ).style,
			).toEqual( {
				display: 'grid',
				gridTemplateColumns: `repeat(auto-fill, minmax(max(min(10rem, 100%), (100% - (${ FALLBACK_BLOCK_GAP } * (4 - 1))) /4), 1fr))`,
				containerType: 'inline-size',
			} );
			expect( FALLBACK_BLOCK_GAP ).toBe(
				'var(--wp--style--block-gap, 0.5em)',
			);
		} );

		it( 'subtracts the block gap, turning a spacing preset into its CSS variable', () => {
			expect(
				getLayoutProps(
					{
						type: 'grid',
						columnCount: 3,
						minimumColumnWidth: '12rem',
						autoFit: true,
					},
					'var:preset|spacing|40',
				).style.gridTemplateColumns,
			).toBe(
				'repeat(auto-fit, minmax(max(min(12rem, 100%), (100% - (var(--wp--preset--spacing--40) * (3 - 1))) /3), 1fr))',
			);
			expect(
				getLayoutProps(
					{ type: 'grid', columnCount: 2, minimumColumnWidth: '6rem' },
					'24px',
				).style.gridTemplateColumns,
			).toBe(
				'repeat(auto-fill, minmax(max(min(6rem, 100%), (100% - (24px * (2 - 1))) /2), 1fr))',
			);
		} );

		it( 'uses the column (left) side of a split block gap', () => {
			expect(
				getLayoutProps(
					{ type: 'grid', columnCount: 3, minimumColumnWidth: '12rem' },
					{ top: '2rem', left: 'var:preset|spacing|20' },
				).style.gridTemplateColumns,
			).toBe(
				'repeat(auto-fill, minmax(max(min(12rem, 100%), (100% - (var(--wp--preset--spacing--20) * (3 - 1))) /3), 1fr))',
			);
		} );

		it( 'uses the theme block gap when a split block gap has no column side', () => {
			expect(
				getLayoutProps(
					{ type: 'grid', columnCount: 3, minimumColumnWidth: '12rem' },
					{ top: '2rem' },
				).style.gridTemplateColumns,
			).toBe(
				`repeat(auto-fill, minmax(max(min(12rem, 100%), (100% - (${ FALLBACK_BLOCK_GAP } * (3 - 1))) /3), 1fr))`,
			);
		} );

		it( 'gives a zero block gap a unit so it works in calc(), as core does', () => {
			expect(
				getLayoutProps(
					{ type: 'grid', columnCount: 3, minimumColumnWidth: '12rem' },
					'0',
				).style.gridTemplateColumns,
			).toBe(
				'repeat(auto-fill, minmax(max(min(12rem, 100%), (100% - (0px * (3 - 1))) /3), 1fr))',
			);
		} );

		it( 'ignores the block gap when the columns do not use it', () => {
			expect(
				getLayoutProps(
					{ type: 'grid', columnCount: 3 },
					'var:preset|spacing|40',
				).style,
			).toEqual( {
				display: 'grid',
				gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
			} );
		} );
	} );
} );
