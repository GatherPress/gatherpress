/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { forwardRef, useState, useMemo } from '@wordpress/element';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalInspectorPopoverHeader as InspectorPopoverHeader } from '@wordpress/block-editor';

/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import VenueNavigator from './VenueNavigator';

const PostPanelRow = forwardRef( ( { className, label, children }, ref ) => {
	return (
		<HStack className={ clsx( 'editor-post-panel__row', className ) } ref={ ref }>
			{ label && (
				<div className="editor-post-panel__row-label">{ label }</div>
			) }
			<div className="editor-post-panel__row-control">{ children }</div>
		</HStack>
	);
} );

function VenuePanelRowToggle( { isOpen, onClick } ) {
	const venueName = 'some venues name';
	return (
		<Button
			size="compact"
			className="editor-event-venue__panel-toggle"
			variant="tertiary"
			aria-expanded={ isOpen }
			aria-label={ sprintf(
				// translators: %s: Current venue link.
				__( 'Change Venue: %s', 'gatherpress' ),
				venueName
			) }
			onClick={ onClick }
		>
			{ venueName }
		</Button>
	);
}

/**
 * Renders the Post Venue Panel component.
 *
 * @param {Object} props Properties of the 'gatherpress/venue-v2'-block.
 * @return {Component} The component to be rendered.
 */
export function VenuePanelRow( props = null ) {
	// Use internal state instead of a ref to make sure that the component
	// re-renders when the popover's anchor updates.
	const [ popoverAnchor, setPopoverAnchor ] = useState( null );

	// Memoize popoverProps to avoid returning a new object every time.
	const popoverProps = useMemo(
		() => ( {
			// Anchor the popover to the middle of the entire row so that it doesn't
			// move around when the label changes.
			anchor: popoverAnchor,
			placement: 'left-start',
			offset: 36,
			shift: true,
		} ),
		[ popoverAnchor ]
	);
	return (
		<PostPanelRow label={ __( 'Venue', 'gatherpress' ) } ref={ setPopoverAnchor }>
			<Dropdown
				popoverProps={ popoverProps }
				contentClassName="editor-event-venue__panel-dialog"
				focusOnMount
				renderToggle={ ( { isOpen, onToggle } ) => (
					<VenuePanelRowToggle isOpen={ isOpen } onClick={ onToggle } />
				) }
				renderContent={ ( { onClose } ) => (
					<div className="editor-event-venue">
						<InspectorPopoverHeader
							title={ __( 'Venue', 'gatherpress' ) }
							onClose={ onClose }
						/>
						<VenueNavigator { ...props } />
					</div>
				) }
			/>
		</PostPanelRow>
	);
}

export default VenuePanelRow;
