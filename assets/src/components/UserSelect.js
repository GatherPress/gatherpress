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

const UserSelect = ( props ) => {
	const { name, option, value } = props.attrs;
	const [ users, setUsers ] = useState( JSON.parse( value ) ?? '[]' );
	const {
		usersList,
	} = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			return {
				usersList: getEntityRecords(
					'root',
					'user',
					{
						per_page: -1,
						context: 'view',
					},
				),
			};
		},
		[
			users,
		],
	);

	const userSuggestions =
		usersList?.reduce(
			( accumulator, user ) => ( {
				...accumulator,
				[ user.name ]: user,
			} ),
			{},
		) ?? {};

	const selectUsers = ( tokens ) => {
		const hasNoSuggestion = tokens.some(
			( token ) =>
			typeof token === 'string' && ! userSuggestions[ token ],
		);

		if ( hasNoSuggestion ) {
			return;
		}

		const allUsers = tokens.map( ( token ) => {
			return typeof token === 'string'
			? userSuggestions[ token ]
			: token;
		} );

		if ( includes( allUsers, null ) ) {
			return false;
		}

		setUsers( allUsers );
	};

	return (
		<>
			<FormTokenField
				key={ option }
				label={ __( 'Select Users', 'gatherpress' ) }
				name={ name }
				value={
					users &&
					users.map( ( item ) => ( {
						id: item.id,
						slug: item.slug,
						value: item.name || item.value,
					} ) )
				}
				suggestions={ Object.keys( userSuggestions ) }
				onChange={ selectUsers }
				maxSuggestions={ 20 }
			/>
			<input
				type="hidden"
				id={ option }
				name={ name }
				value={
					users &&
					JSON.stringify( users.map( ( item ) => ( {
						id: item.id,
						slug: item.slug,
						value: item.name || item.value,
					} ) ) )
				}
			/>
		</>
	);
}

export default UserSelect;
