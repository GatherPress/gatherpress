/**
 * External dependencies.
 */
import { includes } from 'lodash';

/**
 * WordPress dependencies.
 */
import { FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

/**
 * Autocomplete component for GatherPress.
 *
 * This component renders an autocomplete field for selecting posts or other entities.
 * It uses a FormTokenField for the input, allowing users to select multiple items.
 * The selected items are stored in a hidden input field as JSON data.
 *
 * @since 1.0.0
 *
 * @param {Object} props                    - Component props.
 * @param {Object} props.attrs              - Attributes for configuring the Autocomplete field.
 * @param {string} props.attrs.name         - The name attribute for the input field.
 * @param {string} props.attrs.option       - The option attribute for identifying the field.
 * @param {string} props.attrs.value        - The value of the Autocomplete field.
 * @param {Object} props.attrs.fieldOptions - Additional options for configuring the field.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Autocomplete = ( props ) => {
	const { name, option, value, fieldOptions } = props.attrs;
	const showHowTo = 1 !== fieldOptions.limit;
	const [ content, setContent ] = useState( JSON.parse( value ) ?? '[]' );
	const { contentList } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const entityType =
				'user' !== fieldOptions.type ? 'postType' : 'root';
			const kind = fieldOptions.type || 'post';
			return {
				contentList: getEntityRecords( entityType, kind, {
					per_page: -1,
					context: 'view',
				} ),
			};
		},
		[ fieldOptions.type ],
	);

	const contentSuggestions =
		contentList?.reduce(
			( accumulator, item ) => ( {
				...accumulator,
				[ item.title?.rendered || item.name ]: item,
			} ),
			{},
		) ?? {};

	const selectContent = ( tokens ) => {
		const hasNoSuggestion = tokens.some(
			( token ) => 'string' === typeof token && ! contentSuggestions[ token ],
		);

		if ( hasNoSuggestion ) {
			return;
		}

		const allContent = tokens.map( ( token ) => {
			return 'string' === typeof token
				? contentSuggestions[ token ]
				: token;
		} );

		if ( includes( allContent, null ) ) {
			return false;
		}

		setContent( allContent );
	};

	return (
		<>
			<FormTokenField
				key={ option }
				label={ fieldOptions.label || __( 'Select Posts', 'gatherpress' ) }
				name={ name }
				value={
					content &&
					content.map( ( item ) => ( {
						id: item.id,
						slug: item.slug,
						value: item.title?.rendered || item.name || item.value,
					} ) )
				}
				suggestions={ Object.keys( contentSuggestions ) }
				onChange={ selectContent }
				maxSuggestions={ fieldOptions.max_suggestions || 20 }
				maxLength={ fieldOptions.limit || 0 }
				__experimentalShowHowTo={ showHowTo }
			/>
			{ false === showHowTo && (
				<p className="description">
					{ __( 'Choose only one item.', 'gatherpress' ) }
				</p>
			) }
			<input
				type="hidden"
				id={ option }
				name={ name }
				value={
					content &&
					JSON.stringify(
						content.map( ( item ) => ( {
							id: item.id,
							slug: item.slug,
							value:
								item.title?.rendered || item.name || item.value,
						} ) ),
					)
				}
			/>
		</>
	);
};

export default Autocomplete;
