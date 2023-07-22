/**
 * WordPress dependencies.
 */
import { Component } from '@wordpress/element';
import { Button, PanelRow } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { hasEventPast } from '../../../../helpers/event';
import { getFromGlobal, setToGlobal } from '../../../../helpers/globals';

export class AnnounceEvent extends Component {
	constructor(props) {
		super(props);

		this.state = {
			announceEventSent: '0' !== getFromGlobal('event_announced'),
		};
	}

	announce() {
		if (
			global.confirm(
				__(
					'Ready to announce this event to all members?',
					'gatherpress'
				)
			)
		) {
			apiFetch({
				path: '/gatherpress/v1/event/announce/',
				method: 'POST',
				data: {
					post_id: getFromGlobal('post_id'),
					_wpnonce: getFromGlobal('nonce'),
				},
			}).then((res) => {
				const success = res.success ? '1' : '0';

				setToGlobal('event_announced', success);
				this.setState({
					announceEventSent: res.success,
				});
			});
		}
	}

	shouldDisable() {
		return (
			this.state.announceEventSent ||
			'publish' !==
				select('core/editor').getEditedPostAttribute('status') ||
			hasEventPast()
		);
	}

	render() {
		return (
			<section>
				<h3>{__('Communication', 'gatherpress')}</h3>
				<PanelRow>
					<Button
						className="components-button is-primary"
						aria-disabled={this.shouldDisable()}
						onClick={() => this.announce()}
						// disabled={this.shouldDisable()}
					>
						{/*{this.state.announceEventSent*/}
						{/*	? __('Sent', 'gatherpress')*/}
						{/*	: __('Send', 'gatherpress')}*/}
						{__('Notify all members', 'gatherpress')}
					</Button>
				</PanelRow>
			</section>
		);
	}
}
