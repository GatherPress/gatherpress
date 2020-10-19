import React, { Component } from 'react';
import { Button, PanelRow } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { hasEventPast } from '../../../helpers';

const { __ } = wp.i18n;

export class AnnounceEvent extends Component {

	constructor( props ) {
		super( props );

		this.state = {
			announceEventSent: ( '0' !== GatherPress.event_announced )
		};
	}

	announce() {
		if ( confirm( __( 'Ready to announce this event to all members?', 'gatherpress' ) ) ) {
			apiFetch({
				path: '/gatherpress/v1/event/announce/',
				method: 'POST',
				data: {
					post_id: GatherPress.post_id,
					_wpnonce: GatherPress.nonce
				}
			}).then( ( res ) => {
				GatherPress.event_announced = ( res.success ) ? '1' : '0';
				this.setState({
					announceEventSent: res.success
				});
			});

		}
	}

	shouldDisable() {
		return this.state.announceEventSent ||
			'publish' !== wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' ) ||
			hasEventPast();
	}

	render() {
		return (
			<section>
				<h3>{ __( 'Options', 'gatherpress' ) }</h3>
				<PanelRow>
					<span>
						{ __( 'Announce event', 'gatherpress' ) }
					</span>
					<Button
						className     = 'components-button is-primary'
						aria-disabled = { this.shouldDisable() }
						onClick       = { () => this.announce() }
						disabled      = { this.shouldDisable() }
					>
						{ ( this.state.announceEventSent ) ? __( 'Sent', 'gatherpress' ) : __( 'Send', 'gatherpress' ) }
					</Button>
				</PanelRow>
			</section>
		);
	}

}
