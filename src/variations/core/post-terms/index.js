/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { TAXONOMY_STATUS } from '../../../helpers/event-status';

import './style.scss';

/**
 * Event Status.
 *
 * The status an event is in is a term on the event, and core already has a
 * block that renders a post's terms. This points that block at the status
 * taxonomy rather than reimplementing it, so the status inherits every
 * typography, color and spacing control core maintains, and an event holding
 * more than one status renders them the way core renders any term list.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
const CORE_BLOCK = 'core/post-terms';
const CLASS_NAME = 'gatherpress-event-status';

registerBlockVariation( CORE_BLOCK, {
	name: CLASS_NAME,
	title: __( 'Event Status', 'gatherpress' ),
	description: __(
		'Displays whether an event is going ahead, or has been canceled, postponed, rescheduled or moved.',
		'gatherpress'
	),
	category: 'gatherpress',
	attributes: {
		className: CLASS_NAME,
		term: TAXONOMY_STATUS,
	},
	isActive: [ 'term' ],
	scope: [ 'block', 'inserter', 'transform' ],
} );
