/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Flex, FlexItem, PanelRow, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

const VenuePanel = ( props ) => {
	const { venue, setVenue } = props;
	const editPost = useDispatch( 'core/editor' ).editPost;
	const { unlockPostSaving } = useDispatch( 'core/editor' );
	const venueTerm = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( '_gp_venue' ),
	);

	useEffect( () => {
		setVenue( String( venueTerm ) ?? '' );
	}, [] );

	let venues = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords(
			'taxonomy',
			'_gp_venue',
			{
				per_page: -1,
				context: 'view',
			},
		);
	}, [ venue ] );

	if ( venues ) {
		venues = venues.map( ( item ) => ( {
			label: item.name,
			value: item.id,
		} ) );

		venues.unshift( { value: '', label: __( 'Choose a venue', 'gatherpress' ), disabled: true } );
	} else {
		venues = [];
	}

	const updateTerm = ( value ) => {
		setVenue( value );
		editPost( { _gp_venue: [ value ] } );
		unlockPostSaving();
	};

	return (
		<PanelRow>
			<Flex>
				<FlexItem>
					{ __( 'Venue', 'gatherpress' ) }
				</FlexItem>
				<FlexItem>
					<SelectControl
						label={ __( 'Venue', 'gatherpress' ) }
						hideLabelFromVision="true"
						value={ venue }
						onChange={ ( value ) => {
							updateTerm( value );
						} }
						options={ venues }
						style={ { width: '11rem' } }
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
};

export default VenuePanel;
