/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import DoorsTime from '../../components/DoorsTime';
import { isInFSETemplate } from '../../helpers/editor';
import {
	DISABLED_FIELD_OPACITY,
	usePostTypeSupports,
} from '../../helpers/event';
import { formatDoorsTime, resolveDoorsTimes } from './helpers';

/**
 * Edit component for the GatherPress Event Doors block.
 *
 * Shows a placeholder for a time that is not set, so the block stays visible
 * while the event has no doors times. The front end leaves unset times out.
 *
 * @since TBD
 *
 * @param {Object} root0            The props passed to the Edit component.
 * @param {Object} root0.attributes The block attributes.
 * @param {Object} root0.context    Block context data containing postId and postType.
 *
 * @return {JSX.Element} The rendered Edit component.
 */
const Edit = ( { attributes, context } ) => {
	const postId = attributes?.postId ?? context?.postId ?? null;
	const hasExplicitOverride = !! attributes?.postId;

	const { doorsOpen, doorsClose } = useSelect(
		( select ) =>
			resolveDoorsTimes( select, context?.postType, postId, hasExplicitOverride ),
		[ context?.postType, postId, hasExplicitOverride ],
	);

	const supportsEvents = usePostTypeSupports( 'gatherpress-event-date' );
	const currentPostId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId(),
		[],
	);
	// The fields edit the current post, so only offer them when the block shows it.
	const isEvent = supportsEvents && !! postId && postId === currentPostId;

	const blockProps = useBlockProps( {
		style: {
			opacity:
				isInFSETemplate() || doorsOpen || doorsClose
					? 1
					: DISABLED_FIELD_OPACITY,
		},
	} );

	const times = [
		{ which: 'open', label: __( 'Doors open', 'gatherpress' ), time: doorsOpen },
		{ which: 'close', label: __( 'Doors close', 'gatherpress' ), time: doorsClose },
	];

	return (
		<div { ...blockProps }>
			{ times.map( ( { which, label, time } ) => (
				<span key={ which } className={ `gatherpress-event-doors__${ which }` }>
					{ label } { time ? formatDoorsTime( time ) : '—' }
				</span>
			) ) }
			{ isEvent && (
				<InspectorControls>
					<PanelBody title={ __( 'Doors', 'gatherpress' ) }>
						<DoorsTime />
					</PanelBody>
				</InspectorControls>
			) }
		</div>
	);
};

export default Edit;
