/**
 * External dependencies.
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ButtonGroup,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import EventsList from '../../components/EventsList';

const Edit = ( props ) => {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody>
					<p>{ __( 'Event List type', 'gatherpress' ) }</p>
					<ButtonGroup className="block-editor-block-styles__variants">
						<Button
							className={ classnames(
								'block-editor-block-styles__item',
								{
									'is-active': 'upcoming' === attributes.type,
								},
							) }
							variant="secondary"
							label={ __( 'Upcoming', 'gatherpress' ) }
							onClick={ () => {
								setAttributes( { type: 'upcoming' } );
							} }
						>
							<Text
								as="span"
								limit={ 12 }
								ellipsizeMode="tail"
								className="block-editor-block-styles__item-text"
								truncate
							>
								{ __( 'Upcoming', 'gatherpress' ) }
							</Text>
						</Button>
						<Button
							className={ classnames(
								'block-editor-block-styles__item',
								{
									'is-active': 'past' === attributes.type,
								},
							) }
							variant="secondary"
							label={ __( 'Past', 'gatherpress' ) }
							onClick={ () => {
								setAttributes( { type: 'past' } );
							} }
						>
							<Text
								as="span"
								limit={ 12 }
								ellipsizeMode="tail"
								className="block-editor-block-styles__item-text"
								truncate
							>
								{ __( 'Past', 'gatherpress' ) }
							</Text>
						</Button>
					</ButtonGroup>
				</PanelBody>
				<PanelBody>
					<RangeControl
						label={ __(
							'Maximum number of events to display?',
							'gatherpress',
						) }
						min={ 1 }
						max={ 10 }
						value={ parseInt( attributes.maxNumberOfEvents, 10 ) }
						onChange={ ( newVal ) =>
							setAttributes( { maxNumberOfEvents: newVal } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<EventsList
				maxNumberOfEvents={ attributes.maxNumberOfEvents }
				type={ attributes.type }
			/>
		</div>
	);
};

export default Edit;
