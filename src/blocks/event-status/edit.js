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

const STATUS_LABELS = {
	scheduled: __( 'Scheduled', 'gatherpress' ),
	cancelled: __( 'Cancelled', 'gatherpress' ),
	postponed: __( 'Postponed', 'gatherpress' ),
	rescheduled: __( 'Rescheduled', 'gatherpress' ),
	'moved-online': __( 'Moved online', 'gatherpress' ),
};

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
	const postId = context.postId;

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

	const blockProps = useBlockProps( {
		className: `gatherpress-event-status gatherpress-event-status--is-${ status }`,
	} );

	const label = STATUS_LABELS[ status ] || STATUS_LABELS.scheduled;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'gatherpress' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Hide when scheduled', 'gatherpress' ) }
						help={ __(
							'Only display this block when the event is cancelled, postponed, rescheduled, or moved online.',
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
