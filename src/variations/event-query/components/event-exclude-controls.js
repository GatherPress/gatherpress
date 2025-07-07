/**
 * WordPress dependencies
 */
import { ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * A component that lets you exclude the current event from the query
 *
 * @param {*} props
 * @return {Element} EventExcludeControls
 */
export const EventExcludeControls = ( { attributes, setAttributes } ) => {
	const { query: { exclude_current: excludeCurrent } = {} } = attributes;

	const currentPost = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPost();
	}, [] );

	if ( ! currentPost ) {
		return <div>{ __( 'Loading…', 'gatherpress' ) }</div>;
	}

	return (
		<>
			<ToggleControl
				label={ __( 'Exclude Current Event', 'gatherpress' ) }
				checked={ !! excludeCurrent }
				onChange={ ( value ) => {
					setAttributes( {
						query: {
							...attributes.query,
							exclude_current: value ? currentPost.id : 0,
						},
					} );
				} }
			/>
		</>
	);
};
