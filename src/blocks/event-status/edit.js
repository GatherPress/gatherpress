/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getStatusColor, getStatusLabel } from '../../helpers/event-status';

/**
 * Edit component for the GatherPress Event Status block.
 *
 * @since 0.36.0
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @param {Object}   props.context       Block context.
 *
 * @return {JSX.Element} The rendered component.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const { hideScheduled } = attributes;
	// An explicit override wins over the surrounding event, and the control
	// clears it to an empty string rather than removing it.
	const postId = attributes.postId || context.postId;

	const status = useSelect(
		( select ) => {
			if ( ! postId ) {
				return (
					select( 'core/editor' )?.getEditedPostAttribute(
						'gatherpress_status'
					) || 'scheduled'
				);
			}

			const post = select( 'core' ).getEntityRecord(
				'postType',
				context.postType || 'gatherpress_event',
				postId
			);

			return post?.gatherpress_status || 'scheduled';
		},
		[ postId, context.postType ]
	);

	const color = getStatusColor( status );
	const blockProps = useBlockProps( {
		className: `gatherpress-event-status gatherpress-event-status--is-${ status }`,
		// The color travels with the status, so the preview matches whatever
		// the front end will render, including a status a site registered.
		style: color ? { '--gatherpress-status-color': color } : undefined,
	} );

	const label = getStatusLabel( status );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'gatherpress' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Hide when scheduled', 'gatherpress' ) }
						help={ __(
							'Only display this block when the event is canceled, postponed, rescheduled, or moved online.',
							'gatherpress'
						) }
						checked={ hideScheduled }
						onChange={ ( value ) =>
							setAttributes( { hideScheduled: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<span className="gatherpress-event-status__badge">
					{ label }
				</span>
			</div>
		</>
	);
};

export default Edit;
